<?php

namespace RectorPrefix20210614;

if (\class_exists('Tx_Extbase_Security_Exception_InvalidArgumentForRequestHashGeneration')) {
    return;
}
class Tx_Extbase_Security_Exception_InvalidArgumentForRequestHashGeneration
{
}
\class_alias('Tx_Extbase_Security_Exception_InvalidArgumentForRequestHashGeneration', 'Tx_Extbase_Security_Exception_InvalidArgumentForRequestHashGeneration', \false);