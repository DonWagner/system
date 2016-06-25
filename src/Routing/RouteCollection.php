<?php

namespace Nova\Routing;

use Nova\Http\Request;
use Nova\Http\Response;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

use Countable;
use ArrayIterator;
use IteratorAggregate;


class RouteCollection implements Countable, IteratorAggregate
{
    /**
     * An array of the routes keyed by method.
     *
     * @var array
     */
    protected $routes = array();

    /**
     * An flattened array of all of the routes.
     *
     * @var array
     */
    protected $allRoutes = array();

    /**
     * A look-up table of routes by their names.
     *
     * @var array
     */
    protected $nameList = array();

    /**
     * A look-up table of routes by controller action.
     *
     * @var array
     */
    protected $actionList = array();

    /**
     * Add a Route instance to the collection.
     *
     * @param  \Nova\Routing\Route  $route
     * @return \Nova\Routing\Route
     */
    public function add(Route $route)
    {
        $this->addToCollections($route);

        $this->addLookups($route);

        return $route;
    }

    /**
     * Add the given route to the arrays of routes.
     *
     * @param  \Nova\Routing\Route  $route
     * @return void
     */
    protected function addToCollections($route)
    {
        foreach ($route->methods() as $method)
        {
            $this->routes[$method][$route->domain().$route->getUri()] = $route;
        }

        $this->allRoutes[$method.$route->domain().$route->getUri()] = $route;
    }

    /**
     * Add the route to any look-up tables if necessary.
     *
     * @param  \Nova\Routing\Route  $route
     * @return void
     */
    protected function addLookups($route)
    {
        $action = $route->getAction();

        if (isset($action['as'])) {
            $this->nameList[$action['as']] = $route;
        }

        if (isset($action['controller'])) {
            $this->addToActionList($action, $route);
        }
    }

    /**
     * Add a route to the controller action dictionary.
     *
     * @param  array  $action
     * @param  \Nova\Routing\Route  $route
     * @return void
     */
    protected function addToActionList($action, $route)
    {
        if ( ! isset($this->actionList[$action['controller']])) {
            $this->actionList[$action['controller']] = $route;
        }
    }

    /**
     * Find the first route matching a given request.
     *
     * @param  \Nova\Http\Request  $request
     * @return \Nova\Routing\Route
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function match(Request $request)
    {
        $routes = $this->get($request->getMethod());

        $route = $this->check($routes, $request);

        if ( ! is_null($route)) {
            return $route->bind($request);
        }

        $others = $this->checkForAlternateVerbs($request);

        if (count($others) > 0) {
            return $this->getOtherMethodsRoute($request, $others);
        }

        throw new NotFoundHttpException;
    }

    /**
     * Determine if any routes match on another HTTP verb.
     *
     * @param  \Nova\Http\Request  $request
     * @return array
     */
    protected function checkForAlternateVerbs($request)
    {
        $methods = array_diff(Router::$verbs, array($request->getMethod()));

        $others = array();

        foreach ($methods as $method) {
            if ( ! is_null($this->check($this->get($method), $request, false))) {
                $others[] = $method;
            }
        }

        return $others;
    }

    /**
     * Get a route (if necessary) that responds when other available methods are present.
     *
     * @param  \Nova\Http\Request  $request
     * @param  array  $others
     * @return \Nova\Routing\Route
     *
     * @throws \Symfony\Component\Routing\Exception\MethodNotAllowedHttpException
     */
    protected function getOtherMethodsRoute($request, array $others)
    {
        if ($request->method() == 'OPTIONS') {
            return with(new Route('OPTIONS', $request->path(), function() use ($others)
            {
                return new Response('', 200, array('Allow' => implode(',', $others)));

            }))->bind($request);
        } else {
            $this->methodNotAllowed($others);
        }
    }

    /**
     * Throw a method not allowed HTTP exception.
     *
     * @param  array  $others
     * @return void
     *
     * @throws \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
     */
    protected function methodNotAllowed(array $others)
    {
        throw new MethodNotAllowedHttpException($others);
    }

    /**
     * Determine if a route in the array matches the request.
     *
     * @param  array  $routes
     * @param  \Nova\http\Request  $request
     * @param  bool  $includingMethod
     * @return \Nova\Routing\Route|null
     */
    protected function check(array $routes, $request, $includingMethod = true)
    {
        return array_first($routes, function($key, $value) use ($request, $includingMethod)
        {
            return $value->matches($request, $includingMethod);
        });
    }

    /**
     * Get all of the routes in the collection.
     *
     * @param  string|null  $method
     * @return array
     */
    protected function get($method = null)
    {
        if (is_null($method)) return $this->getRoutes();

        return array_get($this->routes, $method, array());
    }

    /**
     * Determine if the route collection contains a given named route.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasNamedRoute($name)
    {
        return ! is_null($this->getByName($name));
    }

    /**
     * Get a route instance by its name.
     *
     * @param  string  $name
     * @return \Nova\Routing\Route|null
     */
    public function getByName($name)
    {
        return isset($this->nameList[$name]) ? $this->nameList[$name] : null;
    }

    /**
     * Get a route instance by its controller action.
     *
     * @param  string  $action
     * @return \Nova\Routing\Route|null
     */
    public function getByAction($action)
    {
        return isset($this->actionList[$action]) ? $this->actionList[$action] : null;
    }

    /**
     * Get all of the routes in the collection.
     *
     * @return array
     */
    public function getRoutes()
    {
        return array_values($this->allRoutes);
    }

    /**
     * Get an iterator for the items.
     *
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->getRoutes());
    }

    /**
     * Count the number of items in the collection.
     *
     * @return int
     */
    public function count()
    {
        return count($this->getRoutes());
    }

}
