<?php

namespace App\Http\Resources;

use App\DTOs\User\UserImportIssueDTO;
use App\DTOs\User\UsersImportReportDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UsersImportReportDTO
 */
class UsersImportReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total'         => $this->total,
            'created'       => $this->created,
            'skipped_count' => count($this->skipped),
            'error_count'   => count($this->errors),
            'warning_count' => count($this->warnings),
            'skipped'         => array_map($this->issue(...), $this->skipped),
            'errors'          => array_map($this->issue(...), $this->errors),
            'warnings'        => array_map($this->issue(...), $this->warnings),
            'ignored_columns' => $this->ignoredColumns,
        ];
    }

    /**
     * @return array{line: int, name: string|null, message: string}
     */
    private function issue(UserImportIssueDTO $issue): array
    {
        return [
            'line'    => $issue->line,
            'name'    => $issue->name,
            'message' => $issue->message,
        ];
    }
}
