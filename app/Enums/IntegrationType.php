<?php

namespace App\Enums;

enum IntegrationType: string
{
    case PRESTASHOP = 'prestashop';
    case PRESTASHOP_DB = 'prestashop-db';
    case CSV_XML_IMPORT = 'csv-xml-import';
}
