<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'folder_id',
        'title',
        'description',
        'latest_version',
        'is_public',
        'is_deleted',
        'is_favorite',
        'expires_at',
        'purge_at',
        'last_accessed_at'
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_deleted' => 'boolean',
        'is_favorite' => 'boolean',
        'expires_at' => 'datetime',
        'purge_at' => 'datetime',
        'last_accessed_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    // public function folder(): BelongsTo
    // {
    //     return $this->belongsTo(Folder::class);
    // }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderBy('version_number', 'desc');
    }

    public function latestVersion(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->where('version_number', $this->latest_version);
    }

    // public function tags(): BelongsToMany
    // {
    //     return $this->belongsToMany(Tag::class, 'document_tags')->withTimestamps();
    // }

    // public function comments(): HasMany
    // {
    //     return $this->hasMany(DocumentComment::class);
    // }

    // public function shares(): HasMany
    // {
    //     return $this->hasMany(DocumentShare::class);
    // }

    // public function accessLogs(): HasMany
    // {
    //     return $this->hasMany(DocumentAccessLog::class);
    // }

    // public function favorites(): HasMany
    // {
    //     return $this->hasMany(Favorite::class);
    // }

    // Get the current version file path
    public function getCurrentFilePath()
    {
        $latestVersion = $this->versions()->first();
        return $latestVersion ? $latestVersion->file_path : null;
    }

    // Get file size in human readable format
    public function getFileSizeAttribute()
    {
        $latestVersion = $this->versions()->first();
        if (!$latestVersion || !$latestVersion->file_size) {
            return 'Unknown';
        }

        $bytes = $latestVersion->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}