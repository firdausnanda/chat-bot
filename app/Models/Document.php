<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
  use HasFactory;

  protected $fillable = [
    'filename',
    'filepath',
    'file_size',
    'pages_count',
    'chunks_count',
    'status',
  ];

  protected $casts = [
    'file_size' => 'integer',
    'pages_count' => 'integer',
    'chunks_count' => 'integer',
  ];
}
