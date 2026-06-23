<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UserAvatarService
{
    private const DISK = 'public';

    private const DIRECTORY = 'avatars';

    /**
     * Public URL for the user's avatar, or null when none is set.
     */
    public function url(User $user): ?string
    {
        $path = $user->avatar;
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        $url = Storage::disk(self::DISK)->url($path);
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
        }

        $version = $user->updated_at?->getTimestamp() ?? time();

        return $url.'?v='.$version;
    }

    public function store(User $user, UploadedFile $file): string
    {
        $this->deleteStoredFile($user->avatar);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $extension = 'jpg';
        }

        return $file->storeAs(
            self::DIRECTORY.'/'.$user->id,
            'avatar.'.$extension,
            self::DISK
        );
    }

    public function delete(User $user): void
    {
        $this->deleteStoredFile($user->avatar);
        $user->avatar = null;
    }

    private function deleteStoredFile(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        if (Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
