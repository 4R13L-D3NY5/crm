<?php

namespace Database\Seeders;

use App\Modules\Contacts\Models\Tag;
use App\Modules\Notes\Models\Note;
use App\Modules\Parameters\Models\Category;
use App\Modules\Parameters\Models\CustomStatus;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Seeder;

class NoteSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::first();
        if (!$organization) {
            return;
        }

        $user = $organization->users()->first();
        if (!$user) {
            return;
        }

        // Obtener categorías o crear de muestra
        $category = Category::where('organization_id', $organization->id)->first();
        $status = CustomStatus::where('organization_id', $organization->id)->first();
        $tag = Tag::where('organization_id', $organization->id)->first();

        // 1. Nota Pinned: Requisitos Medicina
        $note1 = Note::firstOrCreate(
            ['organization_id' => $organization->id, 'shortcut' => 'medicina'],
            [
                'user_id' => $user->id,
                'title' => 'Requisitos de Admisión - Medicina 2026',
                'content' => "Para postular a la carrera de Medicina se requiere:\n1) Fotocopia legalizada de Título de Bachiller.\n2) Certificado de Nacimiento computarizado original.\n3) Fotocopia simple de Cédula de Identidad.\n4) 4 Fotografías 4x4 fondo rojo.\n5) Boleta de depósito de matrícula en caja o transferencia bancaria.",
                'color' => '#1e3a5f', // Navy
                'is_pinned' => true,
                'is_archived' => false,
                'visibility' => 'all',
            ]
        );

        if ($category) {
            $note1->categories()->syncWithoutDetaching([$category->id]);
        }
        if ($status) {
            $note1->customStatuses()->syncWithoutDetaching([$status->id]);
        }
        if ($tag) {
            $note1->tags()->syncWithoutDetaching([$tag->id]);
        }

        // 2. Nota: Planes de Pago y Becas
        $note2 = Note::firstOrCreate(
            ['organization_id' => $organization->id, 'shortcut' => 'becas'],
            [
                'user_id' => $user->id,
                'title' => 'Planes de Pago y Becas Institucionales',
                'content' => "Ofrecemos modalidades de pago flexibles:\n• Pago al contado: 10% de descuento en el arancel total.\n• Pago en 5 cuotas sin recargo de intereses.\n• Beca Bachiller Destacado: Hasta 50% de descuento para promedios mayores a 85 pts.",
                'color' => '#064e3b', // Emerald
                'is_pinned' => false,
                'is_archived' => false,
                'visibility' => 'all',
            ]
        );

        if ($category) {
            $note2->categories()->syncWithoutDetaching([$category->id]);
        }

        // 3. Nota: Ubicación de Sedes y Horarios
        Note::firstOrCreate(
            ['organization_id' => $organization->id, 'shortcut' => 'sedes'],
            [
                'user_id' => $user->id,
                'title' => 'Sedes y Horarios de Atención',
                'content' => "Nuestras oficinas de admisión están abiertas de Lunes a Viernes de 08:00 a 18:30 ininterrumpido, y Sábados de 08:30 a 13:00.\n• Campus Central: Av. Blanco Galindo Km 4.5.\n• Sede Salud: Calle Colombia esquina 25 de Mayo.",
                'color' => '#4c1d95', // Purple
                'is_pinned' => false,
                'is_archived' => false,
                'visibility' => 'all',
            ]
        );
    }
}
