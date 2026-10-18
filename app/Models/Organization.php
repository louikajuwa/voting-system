<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'logo', 'timezone'];

    public function elections(): HasMany
    {
        return $this->hasMany(Election::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
