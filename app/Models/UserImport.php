<?php

namespace App\Models;

use App\Enums\UserImportStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property UserImportStatus $status
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int $total
 * @property int $processed
 * @property array<string, mixed>|null $report
 * @property string|null $error_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserImport extends Model
{
    // Fichier envoyé : disque privé (jamais servi par une adresse publique), supprimé une fois l'import terminé
    public const string FILE_DISK = 'local';

    public const string FILE_DIRECTORY = 'imports';

    protected $fillable = ['user_id', 'status', 'file_path', 'file_name', 'total', 'processed', 'report', 'error_message',];

    protected function casts(): array
    {
        return [
            'status'    => UserImportStatus::class,
            'total'     => 'integer',
            'processed' => 'integer',
            'report'    => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
