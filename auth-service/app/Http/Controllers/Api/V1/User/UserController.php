<?php

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\UserRepositoryInterface;
use Ecomstarter\Core\Response\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;



class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Controller extracts filters from Request
        // Repository knows nothing about HTTP
        $filters = [
            'search'    => $request->input('search'),
            'role'      => $request->input('role'),
            'is_active' => $request->boolean('is_active'),
            'sort'      => $request->input('sort', 'created_at'),
            'direction' => $request->input('direction', 'desc'),
        ];

        $users = $this->userRepository->paginate(
            filters: array_filter($filters),    // remove null values
            perPage: $request->integer('per_page', 15),
        );

        return $this->paginated($users);
    }
}