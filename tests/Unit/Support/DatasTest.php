<?php

declare(strict_types=1);

namespace Paseto\CiotAntt\Tests\Unit\Support;

use DateTimeImmutable;
use Paseto\CiotAntt\Support\Datas;
use Paseto\CiotAntt\Support\Payload;
use Paseto\CiotAntt\Tests\TestCase;

final class DatasTest extends TestCase
{
    public function test_formata_data_de_datetime(): void
    {
        $dt = new DateTimeImmutable('2026-06-23 14:30:05');

        $this->assertSame('2026-06-23', Datas::data($dt));
        $this->assertSame('2026-06-23T14:30:05', Datas::dataHora($dt));
    }

    public function test_mantem_string_ja_formatada(): void
    {
        $this->assertSame('2026-06-23', Datas::data('2026-06-23'));
        $this->assertSame('2026-06-23T14:30:05', Datas::dataHora('2026-06-23T14:30:05'));
    }

    public function test_agora_brasilia_usa_fuso_da_antt(): void
    {
        $br = Datas::agoraBrasilia();
        $utc = new DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->assertSame(Datas::FUSO_ANTT, $br->getTimezone()->getName());
        $this->assertSame(
            $utc->setTimezone(new \DateTimeZone(Datas::FUSO_ANTT))->format(Datas::FORMATO_DATA_HORA),
            Datas::dataHora($br),
        );
    }

    public function test_valida_formatos(): void
    {
        $this->assertTrue(Datas::formatoDataValido('2026-06-23'));
        $this->assertFalse(Datas::formatoDataValido('23/06/2026'));
        $this->assertTrue(Datas::formatoDataHoraValido('2026-06-23T14:30:05'));
        $this->assertFalse(Datas::formatoDataHoraValido('2026-06-23 14:30:05'));
    }

    public function test_payload_decimal_usa_ponto_e_casas_fixas(): void
    {
        $this->assertSame('1500.00', Payload::decimal(1500));
        $this->assertSame('1500.50', Payload::decimal('1500,50'));
        $this->assertSame('1500.50', Payload::decimal('1500.50'));
        $this->assertSame('-23.550000', Payload::decimal(-23.55, 6));
    }

    public function test_payload_decimal_nao_corrompe_separador_de_milhar(): void
    {
        // Formato brasileiro: ponto = milhar, vírgula = decimal.
        $this->assertSame('1500.00', Payload::decimal('1.500,00'));
        $this->assertSame('1234.56', Payload::decimal('1.234,56'));
        $this->assertSame('10000.00', Payload::decimal('10.000,00'));
        // Formato internacional: vírgula = milhar, ponto = decimal.
        $this->assertSame('1500.50', Payload::decimal('1,500.50'));
    }

    public function test_payload_sem_nulos_preserva_zero_e_false(): void
    {
        $resultado = Payload::semNulos([
            'a' => null,
            'b' => 0,
            'c' => false,
            'd' => '',
            'e' => 'x',
        ]);

        $this->assertSame(['b' => 0, 'c' => false, 'd' => '', 'e' => 'x'], $resultado);
    }
}
