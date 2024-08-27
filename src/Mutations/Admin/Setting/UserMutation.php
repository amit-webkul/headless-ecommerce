<?php

namespace Webkul\GraphQLAPI\Mutations\Admin\Setting;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Webkul\User\Repositories\RoleRepository;
use Webkul\User\Repositories\AdminRepository;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Webkul\GraphQLAPI\Validators\CustomException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class UserMutation extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected AdminRepository $adminRepository,
        protected RoleRepository $roleRepository
    ) {
        Auth::setDefaultDriver('admin-api');
    }

    /**
     * Login user resource in storage.
     *
     * @return array
     * @throws CustomException
     */
    public function login(mixed $rootValue, array $args, GraphQLContext $context)
    {
        bagisto_graphql()->validate($args, [
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (! $jwtToken = JWTAuth::attempt([
            'email'    => $args['email'],
            'password' => $args['password'],
        ], $args['remember'] ?? 0)) {
            throw new CustomException(trans('bagisto_graphql::app.admin.settings.users.login-error'));
        }

        try {
            $admin = auth()->guard()->user();

            if (! $admin->status) {
                auth()->guard()->logout();

                throw new CustomException(trans('bagisto_graphql::app.admin.settings.users.activate-warning'));
            }

            return [
                'success'      => true,
                'message'      => trans('bagisto_graphql::app.admin.settings.users.success-login'),
                'access_token' => "Bearer $jwtToken",
                'token_type'   => "Bearer",
                'expires_in'   => Auth::factory()->getTTL() * 60,
                'user'         => $admin,
            ];
        } catch (\Exception $e) {
            throw new CustomException($e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store($rootValue, array $args, GraphQLContext $context)
    {
        if (empty($args['input'])) {
            throw new CustomException(trans('bagisto_graphql::app.admin.response.error.invalid-parameter'));
        }

        $data = $args['input'];

        bagisto_graphql()->validate($data, [
            'name'                  => 'required',
            'email'                 => 'required|email|unique:admins,email',
            'password'              => 'nullable|min:6',
            'password_confirmation' => 'nullable|required_with:password|same:password',
            'role_id'               => 'required',
            'status'                => 'sometimes',
            'image'                 => 'sometimes',
        ]);

        if (! $this->roleRepository->find($data['role_id'])) {
            throw new CustomException(trans('bagisto_graphql::app.admin.settings.roles.not-found'));
        }

        try {
            if (! empty($data['password'])) {
                $data['password'] = bcrypt($data['password']);
                $data['api_token'] = Str::random(80);
            }

            Event::dispatch('user.admin.create.before');

            $imageUrl = $data['image'] ?? '';

            if (! empty($data['image'])) {
                unset($data['image']);
            }

            $admin = $this->adminRepository->create($data);

            bagisto_graphql()->uploadImage($admin, $imageUrl, 'admins/', 'image');

            Event::dispatch('user.admin.create.after', $admin);

            $admin->success = trans('bagisto_graphql::app.admin.settings.users.create-success');

            return $admin;
        } catch (\Exception $e) {
            throw new CustomException($e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update($rootValue, array $args, GraphQLContext $context)
    {
        if (
            empty($args['id'])
            || empty($args['input'])
        ) {
            throw new CustomException(trans('bagisto_graphql::app.admin.response.error.invalid-parameter'));
        }

        $data = $args['input'];

        $id = $args['id'];

        bagisto_graphql()->validate($data, [
            'name'                  => 'required',
            'email'                 => 'required|email|unique:admins,email,'.$id,
            'password'              => 'nullable',
            'password_confirmation' => 'nullable|required_with:password|same:password',
            'status'                => 'sometimes',
            'role_id'               => 'required',
            'image'                 => 'sometimes',
        ]);

        $admin = $this->adminRepository->find($id);

        if (! $admin) {
            throw new CustomException(trans('bagisto_graphql::app.admin.settings.users.not-found'));
        }

        if (! $this->roleRepository->find($data['role_id'])) {
            throw new CustomException(trans('bagisto_graphql::app.admin.settings.roles.not-found'));
        }

        try {
            if (! empty($data['password'])) {
                $isPasswordChanged = true;

                $data['password'] = bcrypt($data['password']);
            }

            $data['status'] = $data['status'] ?? 0;

            Event::dispatch('user.admin.update.before', $id);

            $imageUrl = $data['image'] ?? '';

            if (! empty($data['image'])) {
                unset($data['image']);
            }

            $admin = $this->adminRepository->update($data, $id);

            bagisto_graphql()->uploadImage($admin, $imageUrl, 'admins/', 'image');

            if ($isPasswordChanged) {
                Event::dispatch('user.admin.update-password', $admin);
            }

            Event::dispatch('user.admin.update.after', $admin);

            $admin->success = trans('bagisto_graphql::app.admin.settings.users.update-success');

            return $admin;
        } catch (\Exception $e) {
            throw new CustomException($e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete($rootValue, array $args, GraphQLContext $context)
    {
        if (empty($args['id'])) {
            throw new CustomException(trans('bagisto_graphql::app.admin.response.error.invalid-parameter'));
        }

        $id = $args['id'];

        $admin = $this->adminRepository->find($id);

        if (! $admin) {
            throw new CustomException(trans('bagisto_graphql::app.admin.settings.users.not-found'));
        }

        if ($this->adminRepository->count() == 1) {
            throw new CustomException(trans('bagisto_graphql::app.admin.settings.users.last-delete-error'));
        }

        try {
            Event::dispatch('user.admin.delete.before', $id);

            $this->adminRepository->delete($id);

            Event::dispatch('user.admin.delete.after', $id);

            return [
                'success' => trans('bagisto_graphql::app.admin.settings.users.delete-success'),
            ];
        } catch (\Exception $e) {
            throw new CustomException($e->getMessage());
        }
    }

    /**
     * Logout user resource in storage.
     *
     * @return array
     */
    public function logout()
    {
        auth()->guard()->logout();

        return [
            'success' => true,
            'message' => trans('bagisto_graphql::app.admin.settings.users.success-logout'),
        ];
    }
}
