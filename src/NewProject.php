<?php namespace MyCommand;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class NewProject
 * @package Sercomi
 */
class NewProject extends Command {

    /**
     *
     */
    public function configure()
    {
        $this->setName('new')
            ->setDescription("New PHP project scaffold")
            ->addArgument('name', InputArgument::REQUIRED, "Project folder's name")
            ->addOption('ns', 's', InputOption::VALUE_OPTIONAL ,'Namespace for project', null);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $projectName = $input->getArgument('name');
        $directory = getcwd() . DIRECTORY_SEPARATOR . $projectName;
        $namespace = $input->getOption('ns');
        if ( ! $namespace) $namespace = ucfirst($projectName);

        $this->assertApplicationDoesNotExist($directory, $output);

        $this->makeProjectFolder($directory, $output);
        chdir($directory);

        $this->installPHPSpec($output);

        mkdir($directory . DIRECTORY_SEPARATOR . "src");

        $this->setupNamespace($namespace, $directory, $output);

        $this->setupPHPSpec($directory, $namespace, $output);

        $this->setupPackageJson($directory, $output);

        $this->setupGulpfile($directory, $output);

        $output->writeln("<info>All done!!</info>");

        exit(0);
    }

    /**
     * @param $directory
     * @param OutputInterface $output
     */
    private function assertApplicationDoesNotExist($directory, OutputInterface $output)
    {
        if (is_dir($directory)) {
            $output->writeln("<error>Application already exists!</error>");
            exit(1);
        }
    }

    /**
     * @param $directory
     * @param OutputInterface $output
     */
    private function makeProjectFolder($directory, OutputInterface $output)
    {
        $output->writeln("<info>Creating directory: {$directory}</info>");
        mkdir($directory);
    }

    /**
     * @return string
     */
    private function getGulpfile()
    {
        return <<< EOF
var gulp = require('gulp');
var phpspec = require('gulp-phpspec');
var run = require('gulp-run');
var notify = require('gulp-notify');

gulp.task('test', function() {
    gulp.src('spec/**/*.php')
        .pipe(run('clear'))
        .pipe(phpspec('', { 'verbose': 'v', notify: true }))
        .on('error', notify.onError({
            title: "Crap",
            message: "Your tests FAILED!"
        }))
        .pipe(notify({
            title: "Success",
            message: "All tests have returned green!"
        }));
});

gulp.task('watch', function() {
    gulp.watch(['spec/**/*.php', 'src/**/*.php'], ['test']);
});

gulp.task('default', ['test', 'watch']);
EOF;

    }

    /**
     * @return string
     */
    private function getPackage()
    {
        return <<< EOF
{
  devDependencies: {
    gulp: "^3.8.7",
    gulp-notify: "^1.5.0",
    gulp-phpspec: "^0.2.5",
    gulp-run: "^1.6.4"
  }
}
EOF;

    }

    /**
     * @param OutputInterface $output
     */
    private function installPHPSpec(OutputInterface $output)
    {
        $output->writeln("<info>Installing PHPSpec...</info>");
        exec('composer require phpspec/phpspec:~2.0');
    }

    /**
     * @param $namespace
     * @param $directory
     * @param OutputInterface $output
     */
    private function setupNamespace($namespace, $directory, OutputInterface $output)
    {
        $output->writeln("<info>Setup Namespace...</info>");
        $composer = json_decode(file_get_contents($directory . DIRECTORY_SEPARATOR . 'composer.json'), true);
        $composer['autoload']['psr-4'][$namespace . '\\'] = "src/" . $namespace;
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'composer.json', json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        @exec('composer dump-autoload');
    }

    /**
     * @param $directory
     * @param $namespace
     * @param OutputInterface $output
     */
    private function setupPHPSpec($directory, $namespace, OutputInterface $output)
    {
        $output->writeln("<info>Setup PHPSpec...</info>");
        $namespaceToLower = strtolower($namespace);
        $phpspecYaml = "suites:\n  {$namespaceToLower}_suite:\n    namespace: {$namespace}\n    psr4_prefix: {$namespace}";
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'phpspec.yml', $phpspecYaml);
    }

    /**
     * @param $directory
     * @param OutputInterface $output
     */
    private function setupGulpfile($directory, OutputInterface $output)
    {
        $output->writeln("<info>Setup Gulpfile...</info>");
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'Gulpfile.js', $this->getGulpfile());
    }

    /**
     * @param $directory
     * @param OutputInterface $output
     */
    private function setupPackageJson($directory, OutputInterface $output)
    {
        $output->writeln("<info>Installing gulp packages...</info>");
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'Package.json', "{}");
        @exec('npm install gulp gulp-notify gulp-phpspec gulp-run --save-dev');
    }
}
