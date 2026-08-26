<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class WhatsAppTestCommand extends Command
{
    protected $signature = 'whatsapp:test
                            {telephone : Numéro destinataire (ex. 242044164337 ou +242044164337)}
                            {--message= : Texte du message de test}';

    protected $description = 'Envoie (ou simule) un message WhatsApp de test via le driver COSUD configuré';

    public function handle(WhatsAppService $whatsapp): int
    {
        $telephone = (string) $this->argument('telephone');
        $message = trim((string) ($this->option('message') ?: 'COSUD : message de test WhatsApp. Si vous lisez ceci, le canal fonctionne.'));
        $driver = $whatsapp->driver();

        $this->info("Driver : {$driver}");
        $this->line('Canal réel configuré : '.($whatsapp->isConfigured() ? 'oui' : 'non'));

        if (! $whatsapp->canSend()) {
            $this->error('WhatsApp non configuré pour ce driver.');
            $this->line('log     → simulation locale (ne masque pas les SMS)');
            $this->line('meta    → COSUD_WHATSAPP_META_TOKEN + COSUD_WHATSAPP_META_PHONE_NUMBER_ID');
            $this->line('infobip → COSUD_WHATSAPP_FROM + clé API Infobip');

            return self::FAILURE;
        }

        if ($driver === 'log') {
            $this->warn('Mode log : simulation uniquement (SMS restent actifs). Consultez storage/logs/cosud.log');
        }

        $ok = $whatsapp->send($telephone, $message);

        if ($ok) {
            $this->info('Envoi accepté (ou simulé).');

            return self::SUCCESS;
        }

        $this->error('Échec d’envoi — voir storage/logs/cosud.log');

        return self::FAILURE;
    }
}
