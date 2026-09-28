<?php

return [
    'record_types' => [
        'A', 'AAAA', 'CAA', 'CERT', 'CNAME', 'DNSKEY', 'DS', 'HTTPS', 'LOC',
        'MX', 'NAPTR', 'NS', 'OPENPGPKEY', 'PTR', 'SMIMEA', 'SRV', 'SSHFP',
        'SVCB', 'TLSA', 'TXT', 'URI',
    ],
    'proxy_types' => ['A', 'AAAA', 'CNAME'],
    'priority_types' => ['MX', 'SRV', 'URI'],
    'structured_types' => [
        'CERT', 'DNSKEY', 'DS', 'HTTPS', 'LOC', 'NAPTR', 'SMIMEA', 'SRV',
        'SSHFP', 'SVCB', 'TLSA', 'URI',
    ],
];
