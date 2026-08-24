<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    /**
     * @param  string|null  $type  Filtra por income|expense quando informado.
     */
    public function listFor(User $user, ?string $type = null): Collection
    {
        return $user->categories()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('name')
            ->get();
    }

    public function create(User $user, array $data): Category
    {
        return $user->categories()->create([
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'type' => $data['type'],
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
        ]);
    }

    public function update(Category $category, array $data): Category
    {
        $category->fill(array_intersect_key($data, array_flip(['parent_id', 'name', 'type', 'icon', 'color'])));
        $category->save();

        return $category;
    }

    /**
     * Exclui a categoria. Transações e subcategorias que apontavam pra ela
     * ficam sem categoria (category_id/parent_id viram null via nullOnDelete
     * nas migrations) - não é preciso reatribuir nada manualmente aqui.
     */
    public function delete(Category $category): void
    {
        $category->delete();
    }
}
