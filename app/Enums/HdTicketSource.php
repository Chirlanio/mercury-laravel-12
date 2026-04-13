<?php

namespace App\Enums;

/**
 * Origin of a ticket. Drives channel-specific behavior (outbound reply wiring,
 * analytics, UI badges). Default is WEB for tickets created via the app UI.
 */
enum HdTicketSource: string
{
    case WEB = 'web';
    case WHATSAPP = 'whatsapp';
    case EMAIL = 'email';
    case API = 'api';
    case IMPORT = 'import';

    public function label(): string
    {
        return match ($this) {
            self::WEB => 'Web',
            self::WHATSAPP => 'WhatsApp',
            self::EMAIL => 'E-mail',
            self::API => 'API',
            self::IMPORT => 'Importação',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::WEB => 'globe-alt',
            self::WHATSAPP => 'chat-bubble-left-right',
            self::EMAIL => 'envelope',
            self::API => 'code-bracket',
            self::IMPORT => 'arrow-up-tray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
