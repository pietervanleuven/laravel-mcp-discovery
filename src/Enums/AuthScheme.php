<?php

namespace PieterVanLeuven\McpDiscovery\Enums;

enum AuthScheme: string
{
    case None = 'none';
    case OAuth = 'oauth';
    case Bearer = 'bearer';
}
