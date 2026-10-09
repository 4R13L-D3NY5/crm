<?php

namespace App\Modules\Parameters\Models;

use App\Modules\Tenancy\Models\Organization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'name',
    'is_active',
    'validity_type',
    'start_date',
    'end_date',
    'timezone',
    'schedule_days',
    'auto_reply_enabled',
    'auto_reply_message',
])]
class BusinessHoursConfig extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'business_hours_configs';

    protected $attributes = [
        'name' => 'Horario Laboral General',
        'is_active' => true,
        'validity_type' => 'immediate',
        'timezone' => 'America/La_Paz',
        'auto_reply_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'auto_reply_enabled' => 'boolean',
            'schedule_days' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BusinessHoursConfig $config) {
            if (empty($config->schedule_days)) {
                $config->schedule_days = static::defaultScheduleDays();
            }
            if (empty($config->auto_reply_message)) {
                $config->auto_reply_message = static::defaultAutoReplyMessage();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Días y horarios predeterminados para la semana laboral estándar.
     */
    public static function defaultScheduleDays(): array
    {
        return [
            'monday' => ['enabled' => true, 'start_time' => '08:30', 'end_time' => '18:30'],
            'tuesday' => ['enabled' => true, 'start_time' => '08:30', 'end_time' => '18:30'],
            'wednesday' => ['enabled' => true, 'start_time' => '08:30', 'end_time' => '18:30'],
            'thursday' => ['enabled' => true, 'start_time' => '08:30', 'end_time' => '18:30'],
            'friday' => ['enabled' => true, 'start_time' => '08:30', 'end_time' => '18:30'],
            'saturday' => ['enabled' => false, 'start_time' => '09:00', 'end_time' => '13:00'],
            'sunday' => ['enabled' => false, 'start_time' => '09:00', 'end_time' => '13:00'],
        ];
    }

    /**
     * Mensaje por defecto enviado fuera del horario de atención.
     */
    public static function defaultAutoReplyMessage(): string
    {
        return "¡Hola! Gracias por comunicarte con nosotros. En este momento nos encontramos fuera de nuestro horario de atención habitual. Tu mensaje ha sido registrado y un asesor te responderá a la brevedad tan pronto retomemos actividades. ¡Agradecemos tu paciencia!";
    }

    /**
     * Evalúa si una fecha/hora dada está dentro del horario laboral configurado.
     */
    public function isWithinHours(?Carbon $moment = null): bool
    {
        if (!$this->is_active) {
            return true;
        }

        $tz = $this->timezone ?: 'America/La_Paz';
        $now = $moment ? $moment->copy()->setTimezone($tz) : Carbon::now($tz);

        // 1. Evaluar rango de fechas si la vigencia es programada
        if ($this->validity_type === 'date_range') {
            $today = $now->toDateString();
            if ($this->start_date && $today < $this->start_date->toDateString()) {
                return false;
            }
            if ($this->end_date && $today > $this->end_date->toDateString()) {
                return false;
            }
        }

        // 2. Evaluar día de la semana
        $dayKey = strtolower($now->format('l')); // monday, tuesday, etc.
        $dayConfig = $this->schedule_days[$dayKey] ?? null;

        if (!$dayConfig || empty($dayConfig['enabled'])) {
            return false;
        }

        // 3. Evaluar rango de horas
        $currentTime = $now->format('H:i');
        $start = $dayConfig['start_time'] ?? '00:00';
        $end = $dayConfig['end_time'] ?? '23:59';

        return $currentTime >= $start && $currentTime <= $end;
    }
}
