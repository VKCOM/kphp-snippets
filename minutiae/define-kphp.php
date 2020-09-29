<?php

#ifndef KPHP
define('kphp', 0);
if (false)
#endif
define('kphp', 1);

// afterwards, you can use it like a regular constant:
// header(kphp ? 'Powered by KPHP' : 'Powered by PHP');
