<?php

namespace Tests\Feature;

use App\Models\ModuleAssignment;
use App\Models\Package;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserQuizReviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuizFixture()
    {
        $tenant = Tenant::factory()->create();
        $Package = Package::create([
            'name' => 'Pro-' . uniqid(),
            'slug' => 'pro-' . uniqid(),
            'price_monthly' => 100,
            'max_users' => 100,
            'features' => ['training'],
            'includes_all_modules' => true,
            'is_active' => true,
        ]);
        Subscription::create([
            'tenant_id' => $tenant->id,
            'package_id' => $Package->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
        $module = TrainingModule::create([
            'title' => 'Phishing',
            'content' => 'x',
            'duration_minutes' => 10,
            'status' => 'published',
            'is_active' => true,
        ]);
        $assignment = ModuleAssignment::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'training_module_id' => $module->id,
            'status' => 'assigned',
        ]);

        $quiz = Quiz::create([
            'training_module_id' => $module->id,
            'title' => 'Quiz Phishing',
            'passing_score' => 70,
            'duration_minutes' => 10,
            'is_active' => true,
        ]);

        $q1 = QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question' => 'Apa itu phishing?',
            'options' => ['Serangan email', 'Virus', 'Firewall', 'Antivirus'],
            'correct_index' => 0,
            'explanation' => 'Phishing adalah serangan melalui email palsu.',
        ]);

        $q2 = QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question' => 'Ciri email phishing?',
            'options' => ['Typo', 'Resmi', 'Aman', 'Terenkripsi'],
            'correct_index' => 0,
            'explanation' => 'Email phishing sering memiliki typo.',
        ]);

        return compact('tenant', 'user', 'module', 'assignment', 'quiz', 'q1', 'q2');
    }

    public function test_user_can_review_own_submitted_attempt()
    {
        $fixture = $this->makeQuizFixture();
        extract($fixture);

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => 'submitted',
            'started_at' => now(),
            'submitted_at' => now(),
            'score' => 100,
            'passed' => true,
            'answers' => [$q1->id => 0, $q2->id => 0],
            'question_order' => [$q1->id, $q2->id],
            'option_orders' => [
                $q1->id => [0, 1, 2, 3],
                $q2->id => [0, 1, 2, 3],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('user.training.quiz.review', $attempt->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('User/MyTraining/Review')
            ->has('attempt')
            ->has('quiz')
            ->has('questions', 2)
            ->where('questions.0.question', 'Apa itu phishing?')
            ->where('questions.0.correct_index', 0)
            ->where('questions.0.user_answer_index', 0)
            ->where('questions.0.explanation', 'Phishing adalah serangan melalui email palsu.')
        );
    }

    public function test_user_can_review_expired_attempt()
    {
        $fixture = $this->makeQuizFixture();
        extract($fixture);

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => 'expired',
            'started_at' => now()->subHour(),
            'deadline_at' => now()->subMinutes(30),
            'submitted_at' => now()->subMinutes(30),
            'score' => 50,
            'passed' => false,
            'answers' => [$q1->id => 0, $q2->id => 1],
            'question_order' => [$q1->id, $q2->id],
            'option_orders' => [
                $q1->id => [0, 1, 2, 3],
                $q2->id => [0, 1, 2, 3],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('user.training.quiz.review', $attempt->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('User/MyTraining/Review')
            ->where('attempt.status', 'expired')
        );
    }

    public function test_user_cannot_review_in_progress_attempt()
    {
        $fixture = $this->makeQuizFixture();
        extract($fixture);

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'question_order' => [$q1->id, $q2->id],
            'option_orders' => [
                $q1->id => [0, 1, 2, 3],
                $q2->id => [0, 1, 2, 3],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('user.training.quiz.review', $attempt->id));

        $response->assertStatus(403);
    }

    public function test_user_cannot_review_another_user_attempt()
    {
        $fixture = $this->makeQuizFixture();
        extract($fixture);

        $otherUser = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $otherUser->id,
            'tenant_id' => $tenant->id,
            'status' => 'submitted',
            'started_at' => now(),
            'submitted_at' => now(),
            'score' => 100,
            'passed' => true,
            'answers' => [$q1->id => 0, $q2->id => 0],
            'question_order' => [$q1->id, $q2->id],
            'option_orders' => [
                $q1->id => [0, 1, 2, 3],
                $q2->id => [0, 1, 2, 3],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('user.training.quiz.review', $attempt->id));

        $response->assertStatus(403);
    }

    public function test_review_shows_original_question_order_not_shuffled()
    {
        $fixture = $this->makeQuizFixture();
        extract($fixture);

        // Attempt dengan urutan teracak
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => 'submitted',
            'started_at' => now(),
            'submitted_at' => now(),
            'score' => 100,
            'passed' => true,
            'answers' => [$q1->id => 0, $q2->id => 0],
            'question_order' => [$q2->id, $q1->id], // Teracak
            'option_orders' => [
                $q1->id => [0, 1, 2, 3],
                $q2->id => [0, 1, 2, 3],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('user.training.quiz.review', $attempt->id));

        $response->assertOk();
        
        // Review harus tampilkan urutan asli (q1, q2) bukan urutan teracak
        $questions = $response->viewData('page')['props']['questions'];
        expect(count($questions))->toBe(2);
        // Urutan asli dari database (id asc), bukan question_order
        expect($questions[0]['id'])->toBe($q1->id);
        expect($questions[1]['id'])->toBe($q2->id);
    }

    public function test_review_shows_original_option_order_not_shuffled()
    {
        $fixture = $this->makeQuizFixture();
        extract($fixture);

        // Opsi teracak saat attempt
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => 'submitted',
            'started_at' => now(),
            'submitted_at' => now(),
            'score' => 100,
            'passed' => true,
            'answers' => [$q1->id => 2], // User pilih indeks teracak 2
            'question_order' => [$q1->id, $q2->id],
            'option_orders' => [
                $q1->id => [2, 3, 0, 1], // Opsi teracak
                $q2->id => [0, 1, 2, 3],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('user.training.quiz.review', $attempt->id));

        $response->assertOk();
        
        $questions = $response->viewData('page')['props']['questions'];
        
        // Opsi harus urutan asli
        expect($questions[0]['options'])->toBe(['Serangan email', 'Virus', 'Firewall', 'Antivirus']);
        
        // User jawab indeks teracak 2, yang map ke asli 0 (benar)
        expect($questions[0]['user_answer_index'])->toBe(0);
        expect($questions[0]['correct_index'])->toBe(0);
    }
}
