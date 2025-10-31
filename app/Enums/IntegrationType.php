<?php

namespace App\Enums;

enum IntegrationType: string
{
    case PRESTASHOP = 'prestashop';
    case CSV_XML_IMPORT = 'csv-xml-import';
}
