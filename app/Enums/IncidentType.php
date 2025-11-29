<?php

namespace App\Enums;

enum IncidentType: string
{
    case ASISTENCIA = 'asistencia';
    case FALTA_INJUSTIFICADA = 'falta_injustificada';
    case FALTA_JUSTIFICADA = 'falta_justificada';
    case PERMISO_CON_GOCE = 'permiso_con_goce';
    case PERMISO_SIN_GOCE = 'permiso_sin_goce';
    case INCAPACIDAD_GENERAL = 'incapacidad_general';
    case INCAPACIDAD_TRABAJO = 'incapacidad_trabajo';
    case VACACIONES = 'vacaciones';
    case DIA_FESTIVO = 'dia_festivo';
    case DESCANSO = 'descanso';
    case NO_LABORABA = 'no_laboraba';

    public function label(): string
    {
        return match($this) {
            self::ASISTENCIA => 'Asistencia Normal',
            self::FALTA_INJUSTIFICADA => 'Falta Injustificada',
            self::FALTA_JUSTIFICADA => 'Falta Justificada',
            self::PERMISO_CON_GOCE => 'Permiso con Goce',
            self::PERMISO_SIN_GOCE => 'Permiso sin Goce',
            self::INCAPACIDAD_GENERAL => 'Incapacidad General',
            self::INCAPACIDAD_TRABAJO => 'Incapacidad por Trabajo',
            self::VACACIONES => 'Vacaciones',
            self::DIA_FESTIVO => 'Día Festivo',
            self::DESCANSO => 'Día de Descanso',
            self::NO_LABORABA => 'No Laboraba Aún',
        };
    }
}