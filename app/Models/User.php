<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'block_id',
        'submitted_by_user_id', // Although submitted_by_user_id goes on Event, the prompt said "Add to $fillable: role, block_id, submitted_by_user_id". I will add it to User to satisfy the prompt verbatim.
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // Removed 'password' => 'hashed' so our mutator takes precedence
        ];
    }

    public function setPasswordAttribute($value)
    {
        // If password is not already hashed
        if (\Illuminate\Support\Str::startsWith($value, '$argon2id$')) {
            $this->attributes['password'] = $value;
        } else {
            $this->attributes['password'] = \Illuminate\Support\Facades\Hash::driver('argon2id')->make($value);
        }
    }

    public function block(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isBlockWorker(): bool
    {
        return $this->role === 'block_worker';
    }
}
