<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\Notification;
use App\Models\Site;
use App\Models\SiteInboxMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class FormSubmissionController extends Controller
{
    /**
     * Receive a contact form submission (public endpoint).
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $site = Site::where('slug', $slug)->where('is_active', true)->first();

        if (! $site) {
            return response()->json(['error' => 'Site not found'], 404);
        }

        // Rate limit: 10 submissions per minute per IP
        $key = 'form-submit:'.$request->ip().':'.$slug;

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json(['error' => 'Too many submissions'], 429);
        }

        RateLimiter::hit($key, 60);

        $validated = $request->validate([
            '_form_name' => ['nullable', 'string', 'max:100'],
            '_hp' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:10000'],
            'body' => ['nullable', 'string', 'max:10000'],
            'content' => ['nullable', 'string', 'max:10000'],
            'inquiry' => ['nullable', 'string', 'max:10000'],
            'subject' => ['nullable', 'string', 'max:200'],
            'title' => ['nullable', 'string', 'max:200'],
            'topic' => ['nullable', 'string', 'max:200'],
            'comments' => ['nullable', 'string', 'max:10000'],
            'details' => ['nullable', 'string', 'max:10000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:500'],
            'department' => ['nullable', 'string', 'max:100'],
            'to_email' => ['nullable', 'email', 'max:255'],
        ]);

        $data = [];
        foreach ([
            '_form_name',
            '_hp',
            'email',
            'name',
            'first_name',
            'last_name',
            'message',
            'body',
            'content',
            'inquiry',
            'subject',
            'title',
            'topic',
            'comments',
            'details',
            'phone',
            'company',
            'website',
            'url',
            'department',
            'to_email',
        ] as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }
            $value = $validated[$key];
            if ($value === null || $value === '') {
                continue;
            }
            $data[$key] = $value;
        }

        // Basic honeypot spam detection
        $isSpam = $this->detectSpam($request, $data);

        $submission = FormSubmission::create([
            'site_id' => $site->id,
            'form_name' => (string) ($validated['_form_name'] ?? 'contact'),
            'data' => $data,
            'ip_address' => $request->ip(),
            'is_spam' => $isSpam,
            'created_at' => now(),
        ]);

        if (! $isSpam) {
            Notification::createAlert(
                type: 'form_received',
                title: "New form submission on {$site->name}",
                body: 'From: '.($data['email'] ?? $data['name'] ?? 'Anonymous'),
                siteId: $site->id,
                data: ['submission_id' => $submission->id],
            );

            $fromEmail = isset($data['email']) && filter_var($data['email'], FILTER_VALIDATE_EMAIL)
                ? $data['email']
                : null;
            $fromName = null;
            if (! empty($data['name']) && is_string($data['name'])) {
                $fromName = trim($data['name']);
            } elseif (! empty($data['first_name']) || ! empty($data['last_name'])) {
                $fromName = trim(
                    trim((string) ($data['first_name'] ?? '')).' '.trim((string) ($data['last_name'] ?? ''))
                );
            } elseif ($fromEmail) {
                $fromName = explode('@', $fromEmail)[0];
            }

            SiteInboxMessage::create([
                'site_id' => $site->id,
                'direction' => 'inbound',
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'to_email' => isset($data['to_email']) && is_string($data['to_email'])
                    ? $data['to_email']
                    : null,
                'subject' => SiteInboxMessage::subjectFromFormPayload($data, $submission->form_name),
                'body' => SiteInboxMessage::bodyFromFormPayload($data),
                'is_read' => false,
                'source' => 'form',
            ]);
        }

        Log::info("Form submission received for [{$slug}]", [
            'form_name' => $submission->form_name,
            'is_spam' => $isSpam,
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Submission received',
        ], 201);
    }

    private function detectSpam(Request $request, array $data): bool
    {
        // Honeypot: if a hidden field named '_hp' has a value, it's a bot
        if (! empty($data['_hp'])) {
            return true;
        }

        // Check for common spam patterns in free-text fields
        $body = implode("\n", array_filter([
            $data['message'] ?? null,
            $data['body'] ?? null,
            $data['content'] ?? null,
            $data['inquiry'] ?? null,
            $data['comments'] ?? null,
            $data['details'] ?? null,
        ], fn ($v) => is_string($v) && $v !== ''));

        if ($body !== '') {
            $spamPatterns = [
                '/\b(viagra|casino|crypto|click here|buy now|free money)\b/i',
                '/(http[s]?:\/\/){3,}/i', // More than 2 URLs
            ];

            foreach ($spamPatterns as $pattern) {
                if (preg_match($pattern, $body)) {
                    return true;
                }
            }
        }

        return false;
    }
}
