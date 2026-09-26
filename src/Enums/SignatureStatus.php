<?php

namespace PieterVanLeuven\McpDiscovery\Enums;

enum SignatureStatus: string
{
    case Unsigned = 'unsigned';
    case Valid = 'valid';
    case Invalid = 'invalid';
}
