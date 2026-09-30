<?php

namespace App\Models;

use App\Enums\DocumentLevel;
use App\Enums\DocumentSensitivity;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Document Type Master (SRS §187): how a kind of document behaves.
 */
#[Fillable([
    'code', 'name', 'category', 'level', 'is_reusable', 'expiry_applicable', 'verification_required', 'verification_permission',
    'sensitivity', 'allowed_extensions', 'max_size_kb', 'default_department_id', 'sort_order', 'is_active',
])]
class DocumentType extends Model
{
    use Auditable, HasActiveFlag, HasUserstamps;

    public const CATEGORIES = ['identity', 'address', 'finance', 'payment', 'sales', 'insurance', 'registration', 'inspection', 'delivery', 'other'];

    /**
     * In-memory defaults matching the column defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_reusable' => false, 'expiry_applicable' => false, 'verification_required' => true, 'verification_permission' => 'documents.verify',
        'sensitivity' => 'normal', 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_size_kb' => 5120, 'sort_order' => 0, 'is_active' => true,
    ];

    protected string $auditModule = 'documents';

    protected function casts(): array
    {
        return [
            'level' => DocumentLevel::class,
            'sensitivity' => DocumentSensitivity::class,
            'is_reusable' => 'boolean',
            'expiry_applicable' => 'boolean',
            'verification_required' => 'boolean',
            'max_size_kb' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function isSensitive(): bool
    {
        return $this->sensitivity === DocumentSensitivity::Sensitive;
    }

    /**
     * @return list<string>
     */
    public function extensions(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', strtolower($this->allowed_extensions)))));
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function defaultDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'default_department_id');
    }
}
