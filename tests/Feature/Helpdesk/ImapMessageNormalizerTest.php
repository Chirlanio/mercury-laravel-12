<?php

namespace Tests\Feature\Helpdesk;

use App\Services\Helpdesk\ImapMessageNormalizer;
use Tests\TestCase;

/**
 * Unit coverage for the "raw dict → driver payload" transformation.
 *
 * The Webklex Message → raw dict adapter is NOT tested here (it would
 * require mocking Webklex internals that have strict return types). It is
 * covered by end-to-end manual testing against a real IMAP server. What
 * we test here is the pure transformation: given the extracted parts,
 * produce the exact shape the EmailIntakeDriver expects.
 */
class ImapMessageNormalizerTest extends TestCase
{
    public function test_normalizes_minimal_raw_payload(): void
    {
        $raw = [
            'subject' => 'Problema com a impressora',
            'text_body' => 'Boa tarde, a impressora parou de funcionar.',
            'from_email' => 'User@Test.Com',
            'from_name' => 'User Teste',
            'headers' => [
                'message-id' => 'abc123@mailer.test',
            ],
        ];

        $out = (new ImapMessageNormalizer())->normalizeFromRaw($raw, 'TI@Empresa.Com');

        // Email addresses are lowercased
        $this->assertSame('user@test.com', $out['from_email']);
        $this->assertSame('ti@empresa.com', $out['to_email']);

        $this->assertSame('User Teste', $out['from_name']);
        $this->assertSame('Problema com a impressora', $out['subject']);
        $this->assertSame('Boa tarde, a impressora parou de funcionar.', $out['text_body']);

        // Message-ID is always wrapped in angle brackets
        $this->assertSame('<abc123@mailer.test>', $out['message_id']);
        $this->assertNull($out['in_reply_to']);
        $this->assertSame([], $out['references']);
        $this->assertSame([], $out['attachments']);
    }

    public function test_keeps_existing_angle_brackets_on_message_id(): void
    {
        $out = (new ImapMessageNormalizer())->normalizeFromRaw([
            'headers' => ['message-id' => '<already@wrapped>'],
        ], 'x@y.com');

        $this->assertSame('<already@wrapped>', $out['message_id']);
    }

    public function test_null_from_name_when_blank(): void
    {
        $out = (new ImapMessageNormalizer())->normalizeFromRaw([
            'from_email' => 'user@test.com',
            'from_name' => '   ',
        ], 'x@y.com');

        $this->assertNull($out['from_name']);
    }

    public function test_extracts_reply_headers_into_thread_hints(): void
    {
        $raw = [
            'subject' => 'Re: Problema',
            'text_body' => 'Segue atualização.',
            'from_email' => 'user@test.com',
            'headers' => [
                'message-id' => 'reply1@test',
                'in-reply-to' => '<original@test>',
                'references' => '<thread-root@test> <mid@test> <original@test>',
            ],
        ];

        $out = (new ImapMessageNormalizer())->normalizeFromRaw($raw, 'ti@empresa.com');

        $this->assertSame('<original@test>', $out['in_reply_to']);
        $this->assertSame([
            '<thread-root@test>',
            '<mid@test>',
            '<original@test>',
        ], $out['references']);
    }

    public function test_skips_references_when_header_missing(): void
    {
        $out = (new ImapMessageNormalizer())->normalizeFromRaw([
            'headers' => ['message-id' => 'x@y'],
        ], 'x@y.com');

        $this->assertSame([], $out['references']);
    }

    public function test_falls_back_to_html_body_when_text_is_empty(): void
    {
        $raw = [
            'subject' => 'HTML only',
            'text_body' => '',
            'html_body' => '<p>Olá, <strong>este</strong> é um e-mail em HTML.</p>',
            'from_email' => 'user@test.com',
        ];

        $out = (new ImapMessageNormalizer())->normalizeFromRaw($raw, 'x@y.com');

        $this->assertStringContainsString('este é um e-mail em HTML', $out['text_body']);
        $this->assertStringNotContainsString('<strong>', $out['text_body']);
    }

    public function test_prefers_text_body_when_both_present(): void
    {
        $out = (new ImapMessageNormalizer())->normalizeFromRaw([
            'text_body' => 'plain wins',
            'html_body' => '<p>html loses</p>',
        ], 'x@y.com');

        $this->assertSame('plain wins', $out['text_body']);
    }

    public function test_normalizes_attachments_with_base64_encoding(): void
    {
        $raw = [
            'attachments' => [
                [
                    'name' => 'relatório.pdf',
                    'content_type' => 'application/pdf',
                    'content' => 'conteudo-binario-do-pdf',
                ],
            ],
        ];

        $out = (new ImapMessageNormalizer())->normalizeFromRaw($raw, 'x@y.com');

        $this->assertCount(1, $out['attachments']);
        $att = $out['attachments'][0];
        $this->assertSame('relatório.pdf', $att['name']);
        $this->assertSame('application/pdf', $att['content_type']);
        $this->assertSame(base64_encode('conteudo-binario-do-pdf'), $att['content']);
        $this->assertSame(strlen('conteudo-binario-do-pdf'), $att['size']);
    }

    public function test_skips_attachments_with_empty_content(): void
    {
        $raw = [
            'attachments' => [
                ['name' => 'vazio.txt', 'content_type' => 'text/plain', 'content' => ''],
                ['name' => 'ok.txt', 'content_type' => 'text/plain', 'content' => 'hello'],
            ],
        ];

        $out = (new ImapMessageNormalizer())->normalizeFromRaw($raw, 'x@y.com');

        $this->assertCount(1, $out['attachments']);
        $this->assertSame('ok.txt', $out['attachments'][0]['name']);
    }

    public function test_defaults_missing_attachment_metadata(): void
    {
        $raw = [
            'attachments' => [
                ['content' => 'bytes-here'],
            ],
        ];

        $out = (new ImapMessageNormalizer())->normalizeFromRaw($raw, 'x@y.com');

        $this->assertSame('attachment.bin', $out['attachments'][0]['name']);
        $this->assertSame('application/octet-stream', $out['attachments'][0]['content_type']);
    }
}
