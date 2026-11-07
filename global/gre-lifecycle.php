<?php
declare(strict_types=1);

/** The caller must lock the guide first, matching the API snapshot lock order. */
function app_gre_lifecycle_record(PDO $pdo, int $guideId): ?array
{
    if (!$pdo->inTransaction()) throw new LogicException('El cambio de estado de guía requiere una transacción.');
    try {
        $query=$pdo->prepare('SELECT status FROM tbl_gre_submission WHERE source_id=? FOR UPDATE');
        $query->execute([$guideId]);
        $record=$query->fetch(PDO::FETCH_ASSOC);
        return $record===false?null:$record;
    } catch (PDOException $error) {
        // Existing internal guides remain usable before the additive GRE migration.
        if ((string)$error->getCode()==='42S02') return null;
        throw $error;
    }
}

function app_gre_require_local_cancellation(PDO $pdo, int $guideId): void
{
    if (app_gre_lifecycle_record($pdo,$guideId)!==null) {
        throw new DomainException('La guía ya tiene un envío GRE reservado y no puede anularse localmente. Revisa su estado y el procedimiento SUNAT correspondiente.');
    }
}

function app_gre_require_transit_ready(PDO $pdo, int $guideId): void
{
    $record=app_gre_lifecycle_record($pdo,$guideId);
    if ($record!==null&&!in_array($record['status'],['accepted','observed'],true)) {
        throw new DomainException('La guía tiene un envío GRE pendiente, rechazado o incierto. Consulta su ticket y espera un CDR aceptado antes de iniciar el traslado.');
    }
}
