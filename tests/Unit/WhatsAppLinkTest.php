<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\ClientContact;
use App\Support\Communication\WhatsAppLink;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WhatsAppLinkTest extends TestCase
{
    #[DataProvider('phoneProvider')]
    public function test_it_normalizes_brazilian_numbers(string $raw, string $expected): void
    {
        $this->assertSame($expected, app(WhatsAppLink::class)->digits($raw));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function phoneProvider(): array
    {
        return [
            'mobile with punctuation' => ['(11) 99999-8888', '5511999998888'],
            'already with country code' => ['5511999998888', '5511999998888'],
            'landline 10 digits' => ['1133334444', '551133334444'],
        ];
    }

    public function test_it_rejects_short_numbers(): void
    {
        $this->assertNull(app(WhatsAppLink::class)->digits('1234'));
    }

    public function test_it_builds_wa_me_url_with_encoded_text(): void
    {
        $url = app(WhatsAppLink::class)->url('11999998888', 'Olá Maria, cobrança vencida');

        $this->assertSame(
            'https://wa.me/5511999998888?text='.rawurlencode('Olá Maria, cobrança vencida'),
            $url,
        );
    }

    public function test_it_prefers_primary_whatsapp_on_the_client(): void
    {
        $client = new Client(['display_name' => 'Acme']);
        $client->setRelation('contacts', collect([
            new ClientContact(['whatsapp' => '11911112222', 'phone' => null, 'is_primary' => false]),
            new ClientContact(['whatsapp' => '11988887777', 'phone' => null, 'is_primary' => true]),
        ]));

        $this->assertSame('11988887777', app(WhatsAppLink::class)->phoneFor($client));
    }
}
