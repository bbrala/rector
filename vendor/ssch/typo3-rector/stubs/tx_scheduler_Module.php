<?php

namespace RectorPrefix20210614;

if (\class_exists('tx_scheduler_Module')) {
    return;
}
class tx_scheduler_Module
{
}
\class_alias('tx_scheduler_Module', 'tx_scheduler_Module', \false);