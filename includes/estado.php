<?php
function formato_estado(string $estado): string
{
    return ucwords(strtolower(str_replace('_', ' ', $estado)));
}

function color_estado(string $estado): string
{
    switch (str_replace('_', ' ', $estado)) {
        case 'ACTIVO':              return '#1b5e20';
        case 'PENDIENTE RECEPCION': return '#bf360c';
        case 'EN REVISION':         return '#01579b';
        case 'BAJA':                return '#b71c1c';
        default:                    return '#333333';
    }
}

function formato_accion(string $accion): string
{
    $clave = strtoupper(str_replace(' ', '_', trim($accion)));
    $nombres = [
        'ALTA_AUTOMATICA'   => 'Alta automática',
        'REPORTE_FALLA'     => 'Reporte de falla',
        'RECEPCION_EQUIPO'  => 'Recepción de equipo',
        'DEVOLUCION_EQUIPO' => 'Devolución de equipo',
        'BAJA_EQUIPO'       => 'Baja de equipo',
        'EDICION_EQUIPO'    => 'Edición de equipo',
    ];
    return $nombres[$clave] ?? ucfirst(strtolower(str_replace('_', ' ', $accion)));
}