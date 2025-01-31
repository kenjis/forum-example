<?php

namespace App\Entities;

use App\Concerns\HasReactions;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Shield\Entities\Login;
use CodeIgniter\Shield\Entities\User as ShieldUser;
use CodeIgniter\Shield\Models\LoginModel;

class User extends ShieldUser
{
    use HasReactions;

    // protected $datamap = [];
    // protected $dates   = ['created_at', 'updated_at', 'deleted_at'];
    // protected $casts   = [];

    public function __construct(?array $data = null)
    {
        $casts = [
            'thread_count'          => 'integer',
            'post_count'            => 'integer',
            'two_factor_auth_email' => 'int-bool',
        ];

        $this->casts = [...$this->casts, ...$casts];

        parent::__construct($data);
    }

    public function link(): string
    {
        return route_to('profile', $this->username);
    }

    /**
     * Renders out the user's avatar at the specified size (in pixels)
     *
     * @return string
     */
    public function renderAvatar(int $size = 52)
    {
        // Determine the color for the user based on their
        // for existing users, email address since we know we'll always have that
        // Use default hash if new user or the avatar is used as a placeholder

        $idString = 'default-avatar-hash'; // Default avatar string

        if ($this->id) {
            if (setting('Users.avatarNameBasis') === 'name') {
                $names    = explode(' ', $this->name);
                $idString = $this->first_name
                    ? $names[0][0] . ($names[1][0] ?? '')
                    : $this->username[0] . $this->username[1];
            } else {
                $idString = $this->email[0] . $this->email[1];
            }
        }

        $idString = strtoupper($idString);

        $idValue = str_split($idString);
        array_walk($idValue, static function (&$char) {
            $char = ord($char);
        });
        $idValue = implode('', $idValue);

        $colors = setting('Users.avatarPalette');

        return view('users/_avatar', [
            'user'       => $this,
            'size'       => $size,
            'fontSize'   => 20 * ($size / 52),
            'idString'   => $idString,
            'background' => $colors[$idValue % ($colors === null ? 0 : count($colors))],
        ]);
    }

    /**
     * Generates a link to the user Avatar
     */
    public function avatarLink(?int $size = null): string
    {
        // Default from Gravatar
        if (isset($this->id) && empty($this->avatar) && setting('Users.useGravatar')) {
            $hash = md5(strtolower(trim($this->email)));

            return "https://www.gravatar.com/avatar/{$hash}?" . http_build_query([
                's' => ($size ?? 60),
                'd' => setting(
                    'Users.gravatarDefault'
                ),
            ]);
        }

        return ! empty($this->avatar)
            ? base_url('/uploads/' . $this->avatar)
            : '';
    }

    /**
     * Returns the user's last login record.
     */
    public function lastLogin(): ?Login
    {
        return model(LoginModel::class)->lastLogin($this);
    }

    /**
     * Returns the user's last login records.
     */
    public function logins(int $limit = 10)
    {
        return model(LoginModel::class)
            ->where('user_id', $this->id)
            ->orderBy('date', 'desc')
            ->limit($limit)
            ->find();
    }

    /**
     * Deletes the user's avatar from the file system.
     */
    public function deleteAvatar()
    {
        if (! empty($this->avatar)) {
            service('storage')
                ->disk()
                ->delete($this->avatar);
        }

        $this->avatar = null;
    }

    /**
     * Saves the avatar to the file system.
     */
    public function saveAvatar(UploadedFile $file): string
    {
        $path = 'avatars/' . $this->id . '/' . $file->getRandomName();

        $storage = service('storage');
        $storage->disk()
            ->write($path, file_get_contents($file->getTempName()));

        return $path;
    }
}
