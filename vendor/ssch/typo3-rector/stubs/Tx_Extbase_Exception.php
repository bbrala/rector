<?php

namespace RectorPrefix20210614;

if (\class_exists('Tx_Extbase_Exception')) {
    return;
}
class Tx_Extbase_Exception
{
}
\class_alias('Tx_Extbase_Exception', 'Tx_Extbase_Exception', \false);