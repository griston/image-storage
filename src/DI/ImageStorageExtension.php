<?php

declare(strict_types=1);

namespace SkadminUtils\ImageStorage\DI;

use Nette;
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use SkadminUtils\ImageStorage\ImageStorage;

use function assert;

class ImageStorageExtension extends CompilerExtension
{
    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'data_path' => Expect::string()->required(),
            'data_dir' => Expect::string()->required(),
            'data_path_cache' => Expect::string(''),
            'data_dir_cache' => Expect::string(''),
            'algorithm_file' => Expect::string('sha1_file'),
            'algorithm_content' => Expect::string('sha1'),
            'quality' => Expect::int(85),
            'default_transform' => Expect::string('fit'),
            'noimage_identifier' => Expect::string('noimage/03/no-image.png'),
            'friendly_url' => Expect::bool(false),
        ]);
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        $config  = (array) $this->config;
        $builder->addDefinition($this->prefix('storage'))
            ->setType(ImageStorage::class)
            ->setFactory(ImageStorage::class)
            ->setArguments($config);
    }

    public function beforeCompile(): void
    {
        $builder = $this->getContainerBuilder();

        $latteFactory = $builder->getDefinition('latte.latteFactory');
        assert($latteFactory instanceof Nette\DI\Definitions\FactoryDefinition);
        $latteFactory->getResultDefinition()
            ->addSetup('SkadminUtils\ImageStorage\Macros\Macros::install(?->getCompiler())', ['@self']);
    }
}
