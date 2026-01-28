# Step 5: Verification & Testing

A production API is only as good as its tests. Plugs makes feature testing intuitive and fast.

## 1. Generating a Test

You can generate a test class when creating your controller, or manually:

```bash
php theplugs make:test V1/ProductTest
```

## 2. Writing Feature Tests

Open `tests/Feature/V1/ProductTest.php`. Here we'll simulate real HTTP requests and assert expectations:

```php
namespace Tests\Feature\V1;

use Tests\TestCase;
use App\Models\Product;

class ProductTest extends TestCase
{
    public function test_can_list_products(): void
    {
        // Arrange: Create some data
        Product::factory()->count(3)->create();

        // Act: Hit the endpoint
        $response = $this->getJson('/api/v1/products');

        // Assert: Verify status and structure
        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data')
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['id', 'name', 'sku', 'pricing']
                     ]
                 ]);
    }

    public function test_validation_works(): void
    {
        // Act: Send invalid data
        $response = $this->postJson('/api/v1/products', [
            'name' => '', // Missing name
        ]);

        // Assert: Should return 422
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }
}
```

## 3. Running Your Tests

Run the entire test suite using the CLI:

```bash
php theplugs test
```

Or run a specific file:
```bash
php theplugs test tests/Feature/V1/ProductTest.php
```

## 4. Best Practices for API Testing

*   **Test Success & Failure**: Always test for valid data and edge cases (missing fields, wrong types).
*   **Database Refresh**: Use the `RefreshDatabase` trait to ensure a clean state for every test.
*   **Assert Structure**: Don't just assert the status code; verify that the JSON structure matches your API Resource definition.

---
[Check out the Full Example →](full-example.md)
