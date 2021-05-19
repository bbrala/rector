<?php

declare (strict_types=1);
namespace RectorPrefix20210519;

use Rector\Transform\Rector\MethodCall\MethodCallToStaticCallRector;
use Rector\Transform\ValueObject\MethodCallToStaticCall;
use Ssch\TYPO3Rector\Rector\Experimental\OptionalConstructorToHardRequirementRector;
use RectorPrefix20210519\Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\SymfonyPhpConfig\ValueObjectInliner;
return static function (\RectorPrefix20210519\Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $containerConfigurator) : void {
    $services = $containerConfigurator->services();
    $services->set('typo3_objectmanager_get_to_generalutility_makeinstance')->class(\Rector\Transform\Rector\MethodCall\MethodCallToStaticCallRector::class)->call('configure', [[\Rector\Transform\Rector\MethodCall\MethodCallToStaticCallRector::METHOD_CALLS_TO_STATIC_CALLS => \Symplify\SymfonyPhpConfig\ValueObjectInliner::inline([new \Rector\Transform\ValueObject\MethodCallToStaticCall('RectorPrefix20210519\\TYPO3\\CMS\\Extbase\\Object\\ObjectManagerInterface', 'get', 'RectorPrefix20210519\\TYPO3\\CMS\\Core\\Utility\\GeneralUtility', 'makeInstance')])]]);
    $services->set(\Ssch\TYPO3Rector\Rector\Experimental\OptionalConstructorToHardRequirementRector::class);
};