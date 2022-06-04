<?php

namespace Rakoitde\Tools\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class InitCommand extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'CodeIgniter';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'init';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'create or modifies the .env file with baisc configurations like environment, apllication and database';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'init [arguments] [options]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '-env' => 'Config environment',
        '-app' => 'Config application',
        '-db dbgroup' => 'Config database (dbgroup = default)',
        '-all' => 'Config all',
    ];

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        CLI::write('PHP Version: '. CLI::color(phpversion(), 'yellow'));
        CLI::write('CI Version: '. CLI::color(\CodeIgniter\CodeIgniter::CI_VERSION, 'yellow'));
        CLI::write('APPPATH: '. CLI::color(APPPATH, 'yellow'));
        CLI::write('SYSTEMPATH: '. CLI::color(SYSTEMPATH, 'yellow'));
        CLI::write('ROOTPATH: '. CLI::color(ROOTPATH, 'yellow'));
        CLI::write('Included files: '. CLI::color(count(get_included_files()), 'yellow'));

        $request = \Config\Services::CLIRequest();
        $options = $request->getOptions();
        $option_keys = array_keys($options);

        $this->loadEnv();

        if (in_array("env", $option_keys) || in_array("all", $option_keys)) {
            $this->setChoise('CI_ENVIRONMENT', ['development', 'production']);
            $this->saveEnv();
        }

        if (in_array("app", $option_keys) || in_array("all", $option_keys)) {
            $this->setText('app.baseURL');
            $this->setBool('app.forceGlobalSecureRequests');
            $this->saveEnv();
        }

        if (in_array("db", $option_keys) || in_array("all", $option_keys)) {
            while (!$this->setDatabaseConfig($request->getOption("db") ?? 'default')) {
                $exit = CLI::prompt('Retry config database?', ['y','n']);
                if ($exit=='n') { break; }
            }
        }
        
    }

    private function loadEnv() 
    {

        $env_file = ROOTPATH.'.env';

        if (!is_file($env_file)) {
            copy(ROOTPATH.'env', ROOTPATH.'.env');
        }

        $this->env = explode("\n", file_get_contents($env_file));

        return is_file($env_file);

    }

    private function saveEnv() 
    {

        $env_file = ROOTPATH.'.env';

        return file_put_contents($env_file, implode("\n", $this->env));

    }

    private function setDatabaseConfig(string $dbgroup = 'default'): bool
    {
            CLI::write("Set database config for group '".$dbgroup."'. use '#' for comment out.", 'blue');
            $this->setText('database.'.$dbgroup.'.hostname');
            $this->setText('database.'.$dbgroup.'.database');
            $this->setText('database.'.$dbgroup.'.port');
            $this->setText('database.'.$dbgroup.'.username');
            $this->setText('database.'.$dbgroup.'.password');
            $this->setText('database.'.$dbgroup.'.DBDriver');
            $this->setText('database.'.$dbgroup.'.DBPrefix');
            $this->saveEnv();
            $db = \Config\Database::connect($dbgroup, false);

            try {
                $db->connect();
                return true;
            } 
            catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) 
            {
                CLI::write('DB Connect: '.$e->getMessage(), 'red');
                return false;
            }

    }

    private function setChoise(string $variable, array $choices) 
    {
        $default = $this->getEnv($variable);

        array_unshift($choices, '*'.$default['val']);

        $choice = CLI::promptByKey($variable, $choices);

        if ($choice==0) { return; }

        $this->env[$default['id']]=$default['key'].' = '.$choices[$choice];

    }

    private function setUrl(string $variable) 
    {
        $default = $this->getEnv($variable);

        $url = CLI::prompt($variable, $default['val'], 'required|regex_match[/https?:\/\/(?:w{1,3}\.)?[^\s.]*(?:\.[a-z]+)*(?::\d+)?(?![^<]*(?:<\/\w+>|\/?>))/]');

        $this->env[$default['id']]=$default['key'].' = '.$url;
    }

    private function setText(string $variable) 
    {
        $default = $this->getEnv($variable);

        $commentedout = $default['commentedout'] ? "# " : "";
        $text = CLI::prompt($variable, $commentedout.$default['val']);

        if (substr($text,0,1)=="#") {
            $commentout = "# ";
            $text = trim(substr($text,1) ?? "");
        } else {
            $commentout = "";
            $text = trim($text);
        }

        $this->env[$default['id'] ?? null] = $commentout . $default['key'].' = '.$text;

    }

    private function setBool(string $variable) 
    {
        $default = $this->getEnv($variable);

        $bool = CLI::prompt($variable, [$default['val'],$default['val']=='true' ? 'false' : 'true']);

        $this->env[$default['id']]=$default['key'].' = '.$bool;

    }

    private function getEnv($key) {

        $env['key']=$key;
        $env['val']='';
        $env['exist']=false;
        $env['commentedout']=false;

        $line = preg_grep('/'.$key.'/', $this->env);

        if (count($line) > 0) {

            $env['id']= key($line);
            $parts = explode("=", $line[$env['id']]);
            $env['commentedout'] = substr(trim($parts[0]),0,1)=='#';
            $env['val'] = trim($parts[1]);

            #$parts = explode($this->env[key($line)], "=");
            #$val=$parts[1];

            #$fruit = CLI::promptByKey('CI_ENVIRONMENT:', ['development', 'production', 'The ripe banana']);
            #CLI::write('ENV: '. CLI::color('KEY: '.$key, 'red'));
            #CLI::write('ENV: '. CLI::color('VAL: '.$val, 'red'));

        } 

        return $env;
    }
}
