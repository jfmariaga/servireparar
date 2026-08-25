<?php

namespace Tests\Unit\Support;

use App\Support\Moneda;
use PHPUnit\Framework\TestCase;

class MonedaTest extends TestCase
{
    public function test_formatea_en_pesos_colombianos_sin_decimales(): void
    {
        $this->assertSame('$ 16.000', Moneda::cop(16000));
        $this->assertSame('$ 1.500.000', Moneda::cop(1500000));
        $this->assertSame('$ 0', Moneda::cop(0));
    }

    public function test_redondea_los_centavos(): void
    {
        $this->assertSame('$ 1.501', Moneda::cop(1500.6));
    }

    public function test_valor_nulo_o_vacio_retorna_guion(): void
    {
        $this->assertSame('—', Moneda::cop(null));
        $this->assertSame('—', Moneda::cop(''));
    }
}
