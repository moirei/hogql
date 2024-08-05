# moirei/hogql

This PostHog (HogQL) Laravel Query Builder package enables you to interact with PostHog's HogQL API using Laravel's query builder. This package simplifies the process of building and executing analytical queries, allowing you to leverage Laravel's intuitive query syntax while working with PostHog's powerful data analytics platform.

## Features

- **Familiar Syntax**: Use Laravel's query builder syntax to construct complex queries.
- **Secure Access**: Validate and restrict access to specific tables within PostHog.
- **Seamless Integration**: Execute queries and retrieve results directly from PostHog.
- **Configurable Aliases**: Easily map and alias table and column names for better readability.
- **Eloquent Metrics**: Easily integrate and use with [moirei/eloquent-metrics](https://github.com/moirei/eloquent-metrics).

## Installation

```bash
composer require moirei/hogql
```

Publish the config (optional).

```bash
php artisan vendor:publish --tag="hogql-config"
```

## Setup

After installation, you'll need to obtain your [Product ID](https://us.posthog.com/project/22657/settings/project-details#variables) and a [personal API key](https://posthog.com/docs/api#private-endpoint-authentication). This key will need atleast `query:read` scope.

Update your `.env` file with `POSTHOG_PRODUCT_ID` and `POSTHOG_API_TOKEN` accordingly.

## Usage

```php
use MOIREI\HogQl\Facade as HogQl;
...

HogQl::select(['event_name', 'COUNT(*) as event_count'])
                ->whereBetween('timestamp', ['2024-01-01 12:00:00', '2024-07-28 10:15:39'])
                ->groupBy('event_name')
                ->orderBy('event_count', 'DESC')
                ->get();
```

> Note: the `get()` operation always transforms the raw result. E.g. the query above returns a collection of arrays with `event_name` and `event_count` keys/values.

### Extended usage

**Get a DB Query Builder instance**:

```php
$query = HogQl::query();
// OR
$query = HogQl::query('some_other_table');

$result = $query->get();
```

**Start a query with select statement**:

```php
$query = HogQl::select('properties.$os as os')->where('os', 'iOS');
$result = $query->count();
```

**Referencing aliases**:

Setup aliases in config:

```php
// config/hogql.php
...
    'aliases' => [
        'device' => 'properties.$device',
        'os' => 'properties.$os',
        ...
    ],
```

Reference them via selects:

```php
$query = HogQl::select(['device', 'os']);
$result = $query->get();
```

**Get an Eloquent Query Builder instance**:

Returns eloquent model instances.

```php
$query = HogQl::model();
// OR
$query = HogQl::eloquent();

$result = $query->select(['properties.$current_url', 'properties.$device'])->get();
```

**Parse a query string**:

```php
$query = HogQl::parse('SELECT event FROM events');
$result = $query->where('properties.my_user', $user->id)->count();
```

**Get the results of a string query**:

```php
$response = HogQl::get('SELECT event, COUNT() FROM events GROUP BY event ORDER BY COUNT() DESC');
```

**Get the raw results of a string query**:

Returns the raw original [HogQLQueryResponse](https://posthog.com/docs/hogql#query-api) response.

```php
$response = HogQl::getRaw('SELECT event, COUNT() FROM events GROUP BY event ORDER BY COUNT() DESC');
```

**With moirei/eloquent-metrics**:

> moirei/eloquent-metrics is Chartjs compatible.

```php
$query = HogQl::eloquent()->where('event', '$pageview');

$metrics = Trend::make()
            ->name('Page views')
            ->period('week')
            ->sumByDays($query);
```

## Tests

```bash
composer test
```

## Contribution Guidelines

Any pull requests or discussions are welcome. Note that every pull request providing new feature or correcting a bug should be created with appropriate unit tests.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
