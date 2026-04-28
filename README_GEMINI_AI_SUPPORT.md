# Rehub Gemini AI Support Update

This update changes the AI Assistant from simple rule-based replies to Gemini-powered recovery support.

## 1. Security first

The API key must stay in Laravel `.env` only.
Do not put `GEMINI_API_KEY` inside React Native / Expo files.

If the real key was shared in chat, rotate/regenerate it in Google AI Studio before production.

## 2. Install backend dependency

Laravel normally already includes the HTTP client dependency. If your project does not have it, run:

```bash
composer require guzzlehttp/guzzle
```

## 3. Install mobile Markdown renderer

Because Gemini replies are saved as Markdown, install this in your Expo / React Native app:

```bash
npm install react-native-markdown-display
```

Then restart Expo:

```bash
npx expo start --clear
```

## 4. Backend files to copy

Copy these files into your Laravel backend:

```txt
app/Services/GeminiSupportService.php
app/Http/Controllers/API/AiAssistantController.php
resources/prompts/recovery_support.md
```

## 5. Update config/services.php

Add this inside the returned array in `config/services.php`:

```php
'gemini' => [
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
    'timeout' => env('GEMINI_TIMEOUT', 30),
],
```

## 6. Update .env

Use your real key in Laravel `.env`:

```env
GEMINI_API_KEY=your_real_key_here
GEMINI_MODEL=gemini-2.5-flash
GEMINI_BASE_URL=https://generativelanguage.googleapis.com
GEMINI_TIMEOUT=30
```

Then clear config cache:

```bash
php artisan optimize:clear
php artisan config:clear
```

## 7. Mobile file to copy

Copy this file into your Expo app:

```txt
mobile/app/ai-assistant.tsx -> app/ai-assistant.tsx
```

## 8. Test Laravel

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Then open AI Support in the mobile app and ask:

```txt
I feel stressed and I need support.
```
