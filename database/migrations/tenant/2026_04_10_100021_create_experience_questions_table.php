<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_questions', function (Blueprint $table) {
            $table->id();
            $table->string('milestone', 5); // 45, 90
            $table->string('form_type', 10); // employee, manager
            $table->unsignedTinyInteger('question_order');
            $table->string('question_text', 500);
            $table->string('question_type', 10); // rating, text, yes_no
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);

            $table->index(['milestone', 'form_type', 'is_active']);
        });

        // Seed: 45 days - Manager evaluates Employee
        DB::table('experience_questions')->insert([
            ['milestone' => '45', 'form_type' => 'manager', 'question_order' => 1, 'question_text' => 'Como avalia o aprendizado do colaborador sobre produtos e negocio?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '45', 'form_type' => 'manager', 'question_order' => 2, 'question_text' => 'Como avalia a rapidez na assimilação de sistemas/CRM?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '45', 'form_type' => 'manager', 'question_order' => 3, 'question_text' => 'Como avalia o alinhamento cultural do colaborador?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '45', 'form_type' => 'manager', 'question_order' => 4, 'question_text' => 'Como avalia a performance geral do colaborador?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '45', 'form_type' => 'manager', 'question_order' => 5, 'question_text' => 'Quais os pontos fortes do colaborador?', 'question_type' => 'text', 'is_required' => true, 'is_active' => true],
            ['milestone' => '45', 'form_type' => 'manager', 'question_order' => 6, 'question_text' => 'Quais pontos a desenvolver?', 'question_type' => 'text', 'is_required' => true, 'is_active' => true],
            ['milestone' => '45', 'form_type' => 'manager', 'question_order' => 7, 'question_text' => 'Quais feedbacks ja foram fornecidos ao colaborador?', 'question_type' => 'text', 'is_required' => true, 'is_active' => true],
        ]);

        // Seed: 45 days - Employee evaluates Management
        DB::table('experience_questions')->insert([
            ['milestone' => '45', 'form_type' => 'employee', 'question_order' => 1, 'question_text' => 'Como avalia a recepção pela equipe?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '45', 'form_type' => 'employee', 'question_order' => 2, 'question_text' => 'As responsabilidades do cargo foram apresentadas com clareza?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '45', 'form_type' => 'employee', 'question_order' => 3, 'question_text' => 'Como avalia a disponibilidade do seu gestor?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '45', 'form_type' => 'employee', 'question_order' => 4, 'question_text' => 'Como foi sua experiencia com Pessoas & Cultura/DP?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '45', 'form_type' => 'employee', 'question_order' => 5, 'question_text' => 'Que tipo de apoio inicial teria sido util e nao recebeu?', 'question_type' => 'text', 'is_required' => true, 'is_active' => true],
            ['milestone' => '45', 'form_type' => 'employee', 'question_order' => 6, 'question_text' => 'Qual sua avaliação geral da gestão direta?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
        ]);

        // Seed: 90 days - Manager evaluates Employee
        DB::table('experience_questions')->insert([
            ['milestone' => '90', 'form_type' => 'manager', 'question_order' => 1, 'question_text' => 'Como avalia a evolução no conhecimento desde os 45 dias?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '90', 'form_type' => 'manager', 'question_order' => 2, 'question_text' => 'Como avalia a iniciativa e proatividade?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '90', 'form_type' => 'manager', 'question_order' => 3, 'question_text' => 'Como avalia a adaptação cultural?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '90', 'form_type' => 'manager', 'question_order' => 4, 'question_text' => 'Como avalia a abertura a feedbacks?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '90', 'form_type' => 'manager', 'question_order' => 5, 'question_text' => 'Descreva a evolução do colaborador desde a avaliação de 45 dias.', 'question_type' => 'text', 'is_required' => true, 'is_active' => true],
            ['milestone' => '90', 'form_type' => 'manager', 'question_order' => 6, 'question_text' => 'Como avalia a postura profissional (ética e inteligência emocional)?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '90', 'form_type' => 'manager', 'question_order' => 7, 'question_text' => 'Recomenda a efetivação do colaborador?', 'question_type' => 'yes_no', 'is_required' => true, 'is_active' => true],
        ]);

        // Seed: 90 days - Employee evaluates Management
        DB::table('experience_questions')->insert([
            ['milestone' => '90', 'form_type' => 'employee', 'question_order' => 1, 'question_text' => 'As expectativas do cargo estão claras?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '90', 'form_type' => 'employee', 'question_order' => 2, 'question_text' => 'Voce recebeu orientações de melhoria adequadas?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '90', 'form_type' => 'employee', 'question_order' => 3, 'question_text' => 'Como avalia o acompanhamento diário do seu gestor?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '90', 'form_type' => 'employee', 'question_order' => 4, 'question_text' => 'Voce se sente reconhecido pelo seu trabalho?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '90', 'form_type' => 'employee', 'question_order' => 5, 'question_text' => 'Como avalia a qualidade da comunicação da equipe?', 'question_type' => 'rating', 'is_required' => true, 'is_active' => true],
            ['milestone' => '90', 'form_type' => 'employee', 'question_order' => 6, 'question_text' => 'Que sugestões daria para melhorar a experiência de novos colaboradores?', 'question_type' => 'text', 'is_required' => true, 'is_active' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_questions');
    }
};
