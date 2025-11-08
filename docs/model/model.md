# Model Usage: Model Class

// Define your model
class User extends Model
{
    protected $table = 'users';
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password'];
    protected $searchableColumns = ['name', 'email'];
    protected $casts = [
        'is_active' => 'bool',
        'meta' => 'json'
    ];
}

// Use pagination
$users = User::paginate(20); // Uses $_GET['page']

// Search with filters from request
$results = User::search($_GET);
// Handles: ?page=1&per_page=20&search=john&status=active&sort=created_at&direction=desc

// Manual filtering
$activeUsers = User::filter(['status' => 'active', 'role' => 'admin'])->get();

// Other useful methods
$user = User::findOrFail(1);
$users = User::whereIn('id', [1, 2, 3])->get();
$count = User::count();
User::destroy([1, 2, 3]); // Delete multiple