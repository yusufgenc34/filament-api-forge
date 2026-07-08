<?php

use GraphQL\GraphQL;
use YusufGenc34\FilamentApiForge\Http\Controllers\GraphQlController;
use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use YusufGenc34\FilamentApiForge\Services\GraphQlSchemaService;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

// ── Stubs ──────────────────────────────────────────────────────────────────

class GraphQlPostModel extends Model
{
    protected $table = 'graphql_posts';
    protected $fillable = ['title', 'status'];
}

class GraphQlPostResource
{
    public static function apiConfig(): array
    {
        return [
            'allowed_methods'  => ['index', 'show', 'store', 'update', 'destroy'],
            'allowed_filters'  => ['status'],
            'searchable'       => ['title'],
            'validation_rules' => ['title' => ['required', 'string'], 'status' => ['sometimes', 'string']],
        ];
    }
}

function graphQlService(): GraphQlSchemaService
{
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('discoverForVersion')->andReturn(collect([
        [
            'resource_class' => GraphQlPostResource::class,
            'model_class'    => GraphQlPostModel::class,
            'slug'           => 'graphql-posts',
            'panel_id'       => 'admin',
            'plural_label'   => 'GraphQL Posts',
            'api_config'     => GraphQlPostResource::apiConfig(),
            'versions'       => null,
        ],
    ]));

    return new GraphQlSchemaService($mock);
}

function runGraphQl(string $query, ?array $variables = null): array
{
    $result = GraphQL::executeQuery(graphQlService()->schema(), $query, null, null, $variables);

    return $result->toArray(\GraphQL\Error\DebugFlag::INCLUDE_DEBUG_MESSAGE);
}

beforeEach(function () {
    if (! Schema::hasTable('graphql_posts')) {
        Schema::create('graphql_posts', function ($table) {
            $table->id();
            $table->string('title');
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    GraphQlPostModel::query()->delete();
    config()->set('filament-api-forge.auth.enabled', false);
});

it('lists records with pagination via a generated query field', function () {
    GraphQlPostModel::create(['title' => 'Alpha', 'status' => 'published']);
    GraphQlPostModel::create(['title' => 'Beta', 'status' => 'draft']);

    $result = runGraphQl('{ graphqlPosts(perPage: 10) { total data { id title status } } }');

    expect($result)->not->toHaveKey('errors')
        ->and($result['data']['graphqlPosts']['total'])->toBe(2)
        ->and(collect($result['data']['graphqlPosts']['data'])->pluck('title')->all())
        ->toContain('Alpha', 'Beta');
});

it('filters and searches through generated arguments', function () {
    GraphQlPostModel::create(['title' => 'Laravel rocks', 'status' => 'published']);
    GraphQlPostModel::create(['title' => 'Other', 'status' => 'draft']);

    $filtered = runGraphQl('{ graphqlPosts(status: "published") { total } }');
    $searched = runGraphQl('{ graphqlPosts(search: "Laravel") { total } }');

    expect($filtered['data']['graphqlPosts']['total'])->toBe(1)
        ->and($searched['data']['graphqlPosts']['total'])->toBe(1);
});

it('fetches a single record by id', function () {
    $record = GraphQlPostModel::create(['title' => 'Single', 'status' => 'published']);

    $result = runGraphQl("{ graphqlPost(id: {$record->id}) { id title } }");

    expect($result['data']['graphqlPost']['title'])->toBe('Single');
});

it('creates, updates and deletes records through generated mutations', function () {
    $created = runGraphQl('mutation { createGraphqlPost(title: "Via GraphQL", status: "draft") { id title } }');

    expect($created)->not->toHaveKey('errors');
    $id = $created['data']['createGraphqlPost']['id'];

    expect(GraphQlPostModel::find($id)->title)->toBe('Via GraphQL');

    $updated = runGraphQl("mutation { updateGraphqlPost(id: {$id}, status: \"published\") { status } }");

    expect($updated['data']['updateGraphqlPost']['status'])->toBe('published');

    $deleted = runGraphQl("mutation { deleteGraphqlPost(id: {$id}) }");

    expect($deleted['data']['deleteGraphqlPost'])->toBeTrue()
        ->and(GraphQlPostModel::count())->toBe(0);
});

it('create mutation enforces validation rules', function () {
    $result = runGraphQl('mutation { createGraphqlPost(status: "draft") { id } }');

    expect($result)->toHaveKey('errors')
        ->and(GraphQlPostModel::count())->toBe(0);
});

it('enforces token scopes when auth is enabled', function () {
    config()->set('filament-api-forge.auth.enabled', true);

    $request = Request::create('/api/v1/graphql', 'POST');
    $request->attributes->set('api_forge_token', new ApiForgeToken(['scopes' => ['read']]));
    app()->instance('request', $request);

    $read  = runGraphQl('{ graphqlPosts { total } }');
    $write = runGraphQl('mutation { createGraphqlPost(title: "Nope") { id } }');

    expect($read)->not->toHaveKey('errors')
        ->and($write)->toHaveKey('errors')
        ->and($write['errors'][0]['message'])->toContain("required 'write' scope");
});

it('controller gates the endpoint behind config', function () {
    config()->set('filament-api-forge.graphql.enabled', false);

    $controller = new GraphQlController(graphQlService());
    $request = Request::create('/api/v1/graphql', 'POST', ['query' => '{ __typename }']);

    $response = $controller->execute($request);

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true)['error'])->toBe('graphql_disabled');
});

it('controller executes queries when enabled', function () {
    config()->set('filament-api-forge.graphql.enabled', true);

    GraphQlPostModel::create(['title' => 'Endpoint post']);

    $controller = new GraphQlController(graphQlService());
    $request = Request::create('/api/v1/graphql', 'POST', [
        'query' => '{ graphqlPosts { total } }',
    ]);

    $response = $controller->execute($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['data']['graphqlPosts']['total'])->toBe(1);
});
