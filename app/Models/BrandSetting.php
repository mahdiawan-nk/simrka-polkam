<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'brand_name',
        'app_full_name',
        'institution_name',
        'institution_short_name',
        'tagline',
        'short_description',
        'browser_title',
        'logo_path',
        'logo_light_path',
        'logo_dark_path',
        'logo_compact_path',
        'login_logo_path',
        'favicon_path',
        'document_institution_name',
        'document_logo_path',
        'document_tagline',
        'document_footer_text',
        'updated_by',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
