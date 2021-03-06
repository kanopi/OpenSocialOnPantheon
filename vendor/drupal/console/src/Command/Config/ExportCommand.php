<?php

/**
 * @file
 * Contains \Drupal\Console\Command\Config\ExportCommand.
 */

namespace Drupal\Console\Command\Config;

use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Component\Serialization\Yaml;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Drupal\Console\Command\Shared\CommandTrait;
use Drupal\Console\Style\DrupalStyle;
use Drupal\Core\Config\ConfigManager;


class ExportCommand extends Command
{
    use CommandTrait;

    /** @var ConfigManager  */
    protected $configManager;

    /**
     * ExportCommand constructor.
     * @param ConfigManager $configManager
     */
    public function __construct(ConfigManager $configManager ) {
        $this->configManager = $configManager;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('config:export')
            ->setDescription($this->trans('commands.config.export.description'))
            ->addOption(
                'directory',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.config.export.arguments.directory')
            )
            ->addOption(
                'tar',
                false,
                InputOption::VALUE_NONE,
                $this->trans('commands.config.export.arguments.tar')
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new DrupalStyle($input, $output);

        $directory = $input->getOption('directory');
        $tar = $input->getOption('tar');

        if (!$directory) {
            $directory = config_get_config_directory(CONFIG_SYNC_DIRECTORY);
        }

        if ($tar) {
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            $dateTime = new \DateTime();

            $archiveFile = sprintf(
                '%s/config-%s.tar.gz',
                $directory,
                $dateTime->format('Y-m-d-H-i-s')
            );
            $archiveTar = new ArchiveTar($archiveFile, 'gz');
        }

        try {
            // Get raw configuration data without overrides.
            foreach ($this->configManager->getConfigFactory()->listAll() as $name) {
                $configData = $this->configManager->getConfigFactory()->get($name)->getRawData();
                $configName =  sprintf('%s.yml', $name);
                $ymlData = Yaml::encode($configData);

                if ($tar) {
                    $archiveTar->addString(
                        $configName,
                        $ymlData
                    );
                    continue;
                }

                $configFileName =  sprintf('%s/%s', $directory, $configName);

                $fileSystem = new Filesystem();
                try {
                    $fileSystem->mkdir($directory);
                } catch (IOExceptionInterface $e) {
                    $io->error(
                        sprintf(
                            $this->trans('commands.config.export.messages.error'),
                            $e->getPath()
                        )
                    );
                }
                file_put_contents($configFileName, $ymlData);
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
        }

        $io->info(
          sprintf(
            $this->trans('commands.config.export.messages.directory'),
              $directory
            )
        );
    }
}
