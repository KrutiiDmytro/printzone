<?php

// SecurityBundle + LexikJWTAuthenticationBundle are added in Step 2 (S2S firewall);
// their runtime deps are already installed so the lock stays frozen.
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
];
