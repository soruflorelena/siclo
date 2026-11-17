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