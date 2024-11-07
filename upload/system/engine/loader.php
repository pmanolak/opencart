<?php
/**
 * @package        OpenCart
 *
 * @author         Daniel Kerr
 * @copyright      Copyright (c) 2005 - 2022, OpenCart, Ltd. (https://www.opencart.com/)
 * @license        https://opensource.org/licenses/GPL-3.0
 *
 * @see           https://www.opencart.com
 */
namespace Opencart\System\Engine;
/**
 * Class Loader
 *
 * @mixin \Opencart\System\Engine\Registry
 */
class Loader {
	/**
	 * @var \Opencart\System\Engine\Registry
	 */
	protected \Opencart\System\Engine\Registry $registry;

	/**
	 * Constructor
	 *
	 * @param \Opencart\System\Engine\Registry $registry
	 */
	public function __construct(\Opencart\System\Engine\Registry $registry) {
		$this->registry = $registry;
	}

	/**
	 * __get
	 *
	 * https://www.php.net/manual/en/language.oop5.overloading.php#object.get
	 *
	 * @param string $key
	 *
	 * @return object
	 */
	public function __get(string $key): object {
		return $this->registry->get($key);
	}

	/**
	 * __set
	 *
	 * https://www.php.net/manual/en/language.oop5.overloading.php#object.set
	 *
	 * @param string $key
	 * @param object $value
	 *
	 * @return void
	 */
	public function __set(string $key, object $value): void {
		$this->registry->set($key, $value);
	}

	/**
	 * Controller
	 *
	 * https://wiki.php.net/rfc/variadics
	 *
	 * @param string $route
	 * @param mixed  $args
	 *
	 * @return mixed
	 */
	public function controller(string $route, ...$args) {
		// Sanitize the call
		$route = preg_replace('/[^a-zA-Z0-9_|\/\.]/', '', str_replace('|', '.', $route));

		$trigger = $route;

		// Trigger the pre events
		$this->event->trigger('controller/' . $trigger . '/before', [&$route, &$args]);

		$output = $this->execute('controller/' . $route . (!str_contains($route, '.') ? '.index' : ''), $args);

		// If action cannot be executed, we return an action error object.
		if ($output instanceof \Exception) {
			return $output;
		}

		// Trigger the post events
		$this->event->trigger('controller/' . $trigger . '/after', [&$route, &$args, &$output]);

		return $output;
	}

	/**
	 * Model
	 *
	 * @param string $route
	 *
	 * @return void
	 */
	public function model(string $route): void {
		// Sanitize the call
		$route = preg_replace('/[^a-zA-Z0-9_\/]/', '', $route);

		// Create a new key to store the model obj
		$key = 'model_' . str_replace('/', '_', $route);

		if (!$this->registry->has($key)) {
			// Initialize the class
			$object = $this->factory->model($route);

			if (!$object instanceof \Exception) {
				// Store original object
				$this->registry->set('fallback_' . $key, $object);

				$proxy = new \Opencart\System\Engine\Proxy();

				foreach (get_class_methods($object) as $method) {
					if (substr($method, 0, 2) != '__') {
						$proxy->{$method} = $this->callback($route . '.' . $method);
					}
				}

				// Store proxy object
				$this->registry->set($key, $proxy);
			} else {
				throw new \Exception('Error: Could not load model ' . $route . '!');
			}
		}
	}

	/**
	 * View
	 *
	 * Loads the template file and generates the html code.
	 *
	 * @param string               $route
	 * @param array<string, mixed> $data
	 * @param string               $code
	 *
	 * @return string
	 */
	public function view(string $route, array $data = [], string $code = ''): string {
		// Sanitize the call
		$route = preg_replace('/[^a-zA-Z0-9_\/]/', '', $route);

		$trigger = $route;

		$output = '';

		// Trigger the pre events
		$this->event->trigger('view/' . $trigger . '/before', [&$route, &$data, &$code, &$output]);

		if (!$output) {
			// Make sure it's only the last event that returns an output if required.
			$output = $this->template->render($route, $data, $code);
		}

		// Trigger the post events
		$this->event->trigger('view/' . $trigger . '/after', [&$route, &$data, &$output]);

		return $output;
	}

	/**
	 * Language
	 *
	 * @param string $route
	 * @param string $prefix
	 * @param string $code
	 *
	 * @return array<string, string>
	 */
	public function language(string $route, string $prefix = '', string $code = ''): array {
		// Sanitize the call
		$route = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $route);

		$trigger = $route;

		// Trigger the pre events
		$this->event->trigger('language/' . $trigger . '/before', [&$route, &$prefix, &$code]);

		$output = $this->language->load($route, $prefix, $code);

		// Trigger the post events
		$this->event->trigger('language/' . $trigger . '/after', [&$route, &$prefix, &$code, &$output]);

		return $output;
	}

	/**
	 * Library
	 *
	 * @param string       $route
	 * @param array<mixed> $args
	 *
	 * @return object
	 */
	public function library(string $route, &...$args): object {
		// Sanitize the call
		$route = preg_replace('/[^a-zA-Z0-9_\/]/', '', $route);

		// Create a new key to store the model object
		$key = 'library_' . str_replace('/', '_', $route);

		if (!$this->registry->has($key)) {
			// Initialize the class
			$library = $this->factory->library($route, $args);

			// Store object
			$this->registry->set($key, $library);
		} else {
			$library = $this->registry->get($key);
		}

		return $library;
	}

	/**
	 * Config
	 *
	 * @param string $route
	 *
	 * @return array<string, string>
	 */
	public function config(string $route): array {
		// Sanitize the call
		$route = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $route);

		$trigger = $route;

		// Trigger the pre events
		$this->event->trigger('config/' . $trigger . '/before', [&$route]);

		$output = $this->config->load($route);

		// Trigger the post events
		$this->event->trigger('config/' . $trigger . '/after', [&$route, &$output]);

		return $output;
	}

	/**
	 * Helper
	 *
	 * @param string $route
	 *
	 * @return void
	 */
	public function helper(string $route): void {
		$route = preg_replace('/[^a-zA-Z0-9_\/]/', '', $route);

		if (!str_starts_with($route, 'extension/')) {
			$file = DIR_SYSTEM . 'helper/' . $route . '.php';
		} else {
			$parts = explode('/', substr($route, 10));

			$code = array_shift($parts);

			$file = DIR_EXTENSION . $code . '/system/helper/' . implode('/', $parts) . '.php';
		}

		if (is_file($file)) {
			include_once($file);
		} else {
			throw new \Exception('Error: Could not load helper ' . $route . '!');
		}
	}

	/**
	 * Callback
	 *
	 * @param string $route
	 *
	 * @return callable
	 */
	public function callback($route): callable {
		return function(&...$args) use ($route) {
			$trigger = $route;

			// Trigger the pre events
			$this->event->trigger('model/' . $trigger . '/before', [&$route, &$args]);

			// Find last `/` so we can remove and find the method
			$output = $this->execute('model/' . $route, $args);

			// If action cannot be executed, we return an action error object.
			if ($output instanceof \Exception) {
				throw $output;
			}

			// Trigger the post events
			$this->event->trigger('model/' . $trigger . '/after', [&$route, &$args, &$output]);

			return $output;
		};
	}

	/**
	 * Execute
	 *
	 * @param string $route
	 * @param array  $args
	 *
	 * @return mixed
	 */
	public function execute(string $route, array $args = []): mixed {
		// Separate the type, route and method to call
		preg_match('/(?P<type>^\w+)\/(?P<route>(.+))\.(?P<method>\w+)/', $route, $match);

		$type = $match['type'];
		$route = $match['route'];
		$method = $match['method'];

		// Create a new key to store the model object
		$key = 'fallback_' . $type . '_' . str_replace('/', '_', $route);

		// Stop any magical methods being called
		if (substr($method, 0, 2) == '__') {
			return new \Exception('Error: Calls to magic methods are not allowed!');
		}

		if (!$this->registry->has($key)) {
			$object = call_user_func_array([$this->factory, $type], [$route]);

			$this->registry->set($key, $object);
		} else {
			$object = $this->registry->get($key);
		}

		$callable = [$object, $method];

		if (is_callable($callable)) {
			return $callable(...$args);
		} else {
			return new \Exception('Error: Could not call ' . $type . ' ' . $route . '!');
		}
	}
}
