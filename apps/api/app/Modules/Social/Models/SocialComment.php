<?php

namespace App\Modules\Social\Models;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialComment extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'whatsapp_account_id',
        'contact_id',
        'conversation_id',
        'platform',
        'post_id',
        'comment_id',
        'author_name',
        'author_id',
        'comment_text',
        'reply_text',
        'is_replied',
        'ticket_created',
    ];

    protected $casts = [
        'is_replied' => 'boolean',
        'ticket_created' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }
}
