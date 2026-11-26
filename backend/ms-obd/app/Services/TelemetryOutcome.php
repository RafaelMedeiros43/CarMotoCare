<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Resultado do processamento de uma mensagem de telemetria.
 */
enum TelemetryOutcome: string
{
    /** Leitura guardada, km atualizada e estado do veículo publicado. */
    case Published = 'published';

    /** Leitura guardada, mas a km não subiu (nada publicado). */
    case Stored = 'stored';

    /** Leitura inválida (V1–V5) ou dispositivo desconhecido: descartada. */
    case Discarded = 'discarded';

    /** Erro de infraestrutura (SAPI ou broker): registado em log, sem exceção. */
    case Failed = 'failed';
}
