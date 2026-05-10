<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class ReceiptTemplate extends Model
{
    use LogsActivity;

    protected $fillable = ['layout', 'logo_path'];

    protected $casts = [
        'layout' => 'array',
    ];

    protected $appends = ['logo_url'];

    public static function current(): self
    {
        return self::query()->firstOrCreate(
            ['id' => 1],
            ['layout' => self::defaultLayout()],
        );
    }

    public static function defaultLayout(): array
    {
        $W = 302;
        $blocks = [];
        $y = 8;

        $blocks[] = self::block('logo_1', 'logo', x: 110, y: $y, w: 80, h: 60, style: ['align' => 'center']);
        $y += 68;

        $blocks[] = self::block('company_1', 'company_info', x: 0, y: $y, w: $W, h: null, style: ['align' => 'center']);
        $y += 96;

        $blocks[] = self::block('divider_1', 'divider', x: 0, y: $y, w: $W, h: 8, config: ['style' => 'solid']);
        $y += 14;

        $blocks[] = self::block('doc_title_1', 'doc_title', x: 0, y: $y, w: $W, h: null, style: ['align' => 'center', 'bold' => true, 'size_override' => 'lg']);
        $y += 30;

        $blocks[] = self::block('doc_number_1', 'doc_number', x: 0, y: $y, w: $W, h: null, style: ['align' => 'center', 'bold' => true]);
        $y += 56;

        $blocks[] = self::block('customer_1', 'customer', x: 0, y: $y, w: $W, h: null);
        $y += 60;

        $blocks[] = self::block('items_table_1', 'items_table', x: 0, y: $y, w: $W, h: null, config: [
            'columns' => [
                ['key' => 'qty', 'label' => 'Cant', 'enabled' => true],
                ['key' => 'name', 'label' => 'Descripción', 'enabled' => true],
                ['key' => 'unit', 'label' => 'P.U.', 'enabled' => true],
                ['key' => 'sub', 'label' => 'Sub', 'enabled' => true],
            ],
        ]);
        $y += 110;

        $blocks[] = self::block('totals_1', 'totals', x: 0, y: $y, w: $W, h: null, style: ['align' => 'right'], config: ['show_tax_breakdown' => true]);
        $y += 60;

        $blocks[] = self::block('payments_1', 'payments', x: 0, y: $y, w: $W, h: null);
        $y += 38;

        $blocks[] = self::block('loyalty_1', 'loyalty', x: 0, y: $y, w: $W, h: null);
        $y += 36;

        $blocks[] = self::block('qr_1', 'qr', x: 96, y: $y, w: 110, h: 110, style: ['align' => 'center']);
        $y += 116;

        $blocks[] = self::block('footer_1', 'free_text', x: 0, y: $y, w: $W, h: null, style: ['align' => 'center'], config: ['lines' => ['¡Gracias por su compra!']]);
        $y += 26;

        return [
            'version' => 3,
            'paper_width' => 'thermal_80',
            'font_size' => 'md',
            'canvas_height' => max(400, $y + 16),
            'blocks' => $blocks,
        ];
    }

    private static function block(
        string $id,
        string $type,
        int $x,
        int $y,
        int $w,
        ?int $h,
        array $style = [],
        array $config = [],
        bool $enabled = true,
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'enabled' => $enabled,
            'locked' => false,
            'config' => $config,
            'style' => array_merge([
                'align' => 'left',
                'bold' => false,
                'italic' => false,
                'underline' => false,
                'size_override' => null,
                'color' => null,
                'background' => null,
                'padding' => 0,
                'border_width' => 0,
                'border_color' => null,
                'border_radius' => 0,
            ], $style),
            'position' => [
                'x' => $x,
                'y' => $y,
                'w' => $w,
                'h' => $h,
                'z' => 0,
            ],
        ];
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->logo_path) {
                return null;
            }

            return tenant_asset($this->logo_path);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['layout', 'logo_path'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
