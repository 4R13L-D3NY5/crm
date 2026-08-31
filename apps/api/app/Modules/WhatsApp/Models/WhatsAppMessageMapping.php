<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Tenancy\Models\Organization;
use Database\Factories\WhatsAppMessageMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'whatsapp_account_id',
    'contact_id',
    'conversation_id',
    'message_id',
    'provider_message_id',
    'direction',
    'status',
    'from_phone',
    'to_phone_number_id',
    'payload',
])]
class WhatsAppMessageMapping extends Model
{
    /** @use HasFactory<WhatsAppMessageMappingFactory> */
    use HasFactory, HasUlids;

    protected $table = 'whatsapp_message_mappings';

    protected static function newFactory(): WhatsAppMessageMappingFactory
    {
        return WhatsAppMessageMappingFactory::new();
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
