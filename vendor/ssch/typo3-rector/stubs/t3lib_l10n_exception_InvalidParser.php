<?php

namespace RectorPrefix20210930;

if (\class_exists('t3lib_l10n_exception_InvalidParser')) {
    return;
}
class t3lib_l10n_exception_InvalidParser
{
}
\class_alias('t3lib_l10n_exception_InvalidParser', 't3lib_l10n_exception_InvalidParser', \false);
