<?php

namespace Bpocallaghan\Generators\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Composer;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Console\GeneratorCommand as LaravelGeneratorCommand;

abstract class GeneratorCommand extends LaravelGeneratorCommand
{

	/**
	 * @var Composer
	 */
	protected $composer;

	/**
	 * The resource argument
	 *
	 * @var string
	 */
	protected $resource = "";

	/**
	 * Settings of the file to be generated
	 *
	 * @var array
	 */
	protected $settings = [];

	/**
	 * The url for the new generated file
	 *
	 * @var string
	 */
	protected $url = "";

	function __construct(Filesystem $files, Composer $composer)
	{
		parent::__construct($files);

		$this->composer = $composer;
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$this->call('generate:file', [
			'name'    => $this->getArgumentName(),
			'--type'  => strtolower($this->type),
			'--stub'  => $this->getOptionStub(),
			'--plain' => $this->getOptionPlain()
		]);
	}

	/**
	 * Only return the name of the file
	 * Ignore the path / namespace of the file
	 *
	 * @return array|mixed|string
	 */
	protected function getArgumentNameOnly()
	{
		$name = $this->getArgumentName();

		if (str_contains($name, '/'))
		{
			$name = str_replace('/', '.', $name);
		}

		if (str_contains($name, '\\'))
		{
			$name = str_replace('\\', '.', $name);
		}

		if (str_contains($name, '.'))
		{
			return substr($name, strrpos($name, '.') + 1);
		}

		return $name;
	}

	/**
	 * Return the path of the file
	 *
	 * @param bool $withName
	 * @return array|mixed|string
	 */
	protected function getArgumentPath($withName = false)
	{
		$name = $this->getArgumentName();

		if (str_contains($name, '.'))
		{
			$name = str_replace('.', '/', $name);
		}

		if (str_contains($name, '\\'))
		{
			$name = str_replace('\\', '/', $name);
		}

		// ucfirst char, for correct namespace
		$name = implode('/', array_map('ucfirst', explode('/', $name)));

		// if we need to keep lowercase
		if ($this->settings['path_format'] === 'strtolower')
		{
			$name = implode('/', array_map('strtolower', explode('/', $name)));
		}

		// if we want the path with name
		if ($withName)
		{
			return $name . '/';
		}

		if (str_contains($name, '/'))
		{
			return substr($name, 0, strrpos($name, '/') + 1);
		}

		return '';
	}


	/**
	 * Get the resource name
	 *
	 * @param      $name
	 * @param bool $format
	 * @return string
	 */
	protected function getResourceName($name, $format = true)
	{
		// we assume its already formatted to resource name
		if($name && $format === false)
		{
			return $name;
		}

		$name = isset($name) ? $name : $this->resource;

		return str_singular(strtolower(class_basename($name)));
	}

	/**
	 * Get the name for the model
	 *
	 * @param null $name
	 * @return string
	 */
	protected function getModelName($name = null)
	{
		return ucwords(camel_case($this->getResourceName($name)));
	}

	/**
	 * Get the name for the controller
	 *
	 * @param null $name
	 * @param bool $format
	 * @return string
	 */
	protected function getControllerName($name = null, $format = true)
	{
		return ucwords(camel_case(str_replace($this->settings['postfix'], '', $this->getResourceName($name, $format))));
	}

	/**
	 * Get the name for the seed
	 *
	 * @param null $name
	 * @return string
	 */
	protected function getSeedName($name = null)
	{
		return ucwords(camel_case(str_replace($this->settings['postfix'], '', $this->getResourceName($name))));
	}

	/**
	 * Get the name of the collection
	 *
	 * @param null $name
	 * @return string
	 */
	protected function getCollectionName($name = null)
	{
		return str_plural($this->getResourceName($name));
	}

	/**
	 * Get the path to the view file
	 *
	 * @param $name
	 * @return string
	 */
	protected function getViewPath($name)
	{
		$name = implode('.', array_map('str_plural', explode('/', $name)));

		return strtolower(rtrim(ltrim($name, '.'), '.'));
	}

	/**
	 * Get the table name
	 *
	 * @param $name
	 * @return string
	 */
	protected function getTableName($name)
	{
		return str_plural(snake_case(class_basename($name)));
	}

	/**
	 * Get name of file/class with the pre and post fix
	 *
	 * @param $name
	 * @return string
	 */
	protected function getFileNameComplete($name)
	{
		return $this->settings['prefix'] . $name . $this->settings['postfix'];
	}

	/**
	 * Get the default namespace for the class.
	 *
	 * @param  string $rootNamespace
	 * @return string
	 */
	protected function getDefaultNamespace($rootNamespace)
	{
		return $rootNamespace . config('generators.' . strtolower($this->type) . '_namespace');
	}

	/**
	 * Get the stub file for the generator.
	 *
	 * @return string
	 */
	protected function getStub()
	{
		$key = $this->getOptionStubKey();

		// get the stub path
		$stub = config('generators.' . $key);

		if (is_null($stub))
		{
			$this->error('The stub does not exist in the config file - "' . $key . '"');
			exit;
		}

		return $stub;
	}

	/**
	 * Get the key where the stub is located
	 *
	 * @return string
	 */
	protected function getOptionStubKey()
	{
		$plain = $this->option('plain');
		$stub = $this->option('stub') . ($plain ? '_plain' : '') . '_stub';

		// if no stub, we assume its the same as the type
		if (is_null($this->option('stub')))
		{
			$stub = $this->option('type') . ($plain ? '_plain' : '') . '_stub';
		}

		return $stub;
	}

	/**
	 * Get the argument name of the file that needs to be generated
	 * If settings exist, remove the postfix from the file
	 *
	 * @return array|mixed|string
	 */
	protected function getArgumentName()
	{
		if ($this->settings)
		{
			return str_replace($this->settings['postfix'], '', $this->argument('name'));
		}

		return $this->argument('name');
	}

	/**
	 * Get the value for the force option
	 *
	 * @return array|string
	 */
	protected function getOptionForce()
	{
		return $this->option('force');
	}

	/**
	 * Get the value for the plain option
	 *
	 * @return array|string
	 */
	protected function getOptionPlain()
	{
		return $this->option('plain');
	}

	/**
	 * Get the value for the stub option
	 *
	 * @return array|string
	 */
	protected function getOptionStub()
	{
		return $this->option('stub');
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return [
			['name', InputArgument::REQUIRED, 'The name of class being generated.'],
		];
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return [
			['plain', null, InputOption::VALUE_NONE, 'Generate an empty class.'],
			['force', null, InputOption::VALUE_NONE, 'Warning: Overide file if it already exist'],
			['stub', null, InputOption::VALUE_OPTIONAL, 'The name of the view stub you would like to generate.'],
		];
	}
}