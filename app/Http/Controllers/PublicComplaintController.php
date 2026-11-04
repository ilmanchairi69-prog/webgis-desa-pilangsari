<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublicComplaintController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'submission_key' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9][0-9 ()-]{7,22}$/'],
            'category' => ['required', Rule::in(array_keys(Complaint::CATEGORIES))],
            'description' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf', 'extensions:jpg,jpeg,png,webp,pdf'],
        ]);
        $existing = Complaint::where('submission_key', $data['submission_key'])->first();
        if ($existing) {
            return response()->json(['ticket' => $existing->ticket_number, 'next_key' => (string) Str::uuid()]);
        }
        $secret = config('services.turnstile.secret');
        if (! $secret || ! config('services.turnstile.site_key')) {
            return response()->json(['message' => 'Layanan pengaduan belum tersedia. Silakan coba kembali nanti.'], 503);
        }
        $request->validate(['cf-turnstile-response' => ['required', 'string', 'max:2048']]);
        try {
            $verification = Http::asForm()->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', ['secret' => $secret, 'response' => $request->input('cf-turnstile-response')]);
            $verified = $verification->successful() && $verification->json('success') === true;
        } catch (ConnectionException) {
            return response()->json(['message' => 'Layanan verifikasi sementara tidak dapat dihubungi. Silakan coba lagi.'], 503);
        }
        if (! $verification->successful()) {
            return response()->json(['message' => 'Layanan verifikasi sementara tidak dapat dihubungi. Silakan coba lagi.'], 503);
        }
        if (! $verified) {
            throw ValidationException::withMessages(['cf-turnstile-response' => 'Verifikasi keamanan tidak berhasil atau telah kedaluwarsa. Silakan ulangi verifikasi.']);
        }
        $path = null;
        try {
            if ($request->hasFile('attachment')) {
                $path = $request->file('attachment')->store('complaints', 'local');
                if (! $path) {
                    throw new \RuntimeException('Attachment could not be stored.');
                }
            }
            $complaint = Complaint::create(['submission_key' => $data['submission_key'], 'ticket_number' => 'ADU-'.strtoupper(Str::random(20)), 'name' => $data['name'], 'phone' => $data['phone'], 'category' => $data['category'], 'description' => $data['description'], 'attachment_path' => $path, 'status' => 'new']);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            $existing = Complaint::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                return response()->json(['ticket' => $existing->ticket_number, 'next_key' => (string) Str::uuid()]);
            }
            throw $exception;
        }

        return response()->json(['ticket' => $complaint->ticket_number, 'next_key' => (string) Str::uuid()], 201);
    }
}
