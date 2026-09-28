<?php

namespace Database\Seeders;

use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Flag;
use App\Models\Module;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Notifications\CardAssigned;
use App\Notifications\CardCommented;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dados de demonstração (nunca em produção): equipe, flags, 5 projetos com
 * sprints em todos os estados, dezenas de cards com histórico, comentários,
 * anexos e notificações. Determinístico (mt_srand fixo): re-rodar o seed em um
 * banco limpo gera sempre o mesmo cenário.
 */
class DemoSeeder extends Seeder
{
    private const SEED = 2026;

    /** Usuários de demonstração (senha `password` para todos). */
    private const USERS = [
        ['key' => 'dev', 'name' => 'Geovanne Lopes', 'email' => 'dev@flowtree.test', 'position' => 'Desenvolvedor Full-stack', 'modules' => ['tasks']],
        ['key' => 'ana', 'name' => 'Ana Souza', 'email' => 'ana@flowtree.test', 'position' => 'Product Owner', 'modules' => ['tasks', 'settings']],
        ['key' => 'bruno', 'name' => 'Bruno Lima', 'email' => 'bruno@flowtree.test', 'position' => 'Desenvolvedor Front-end', 'modules' => ['tasks']],
        ['key' => 'carla', 'name' => 'Carla Mendes', 'email' => 'carla@flowtree.test', 'position' => 'Desenvolvedora Back-end', 'modules' => ['tasks']],
        ['key' => 'diego', 'name' => 'Diego Rocha', 'email' => 'diego@flowtree.test', 'position' => 'Analista de QA', 'modules' => ['tasks']],
        ['key' => 'elisa', 'name' => 'Elisa Martins', 'email' => 'elisa@flowtree.test', 'position' => 'UX/UI Designer', 'modules' => ['tasks']],
        ['key' => 'felipe', 'name' => 'Felipe Costa', 'email' => 'felipe@flowtree.test', 'position' => 'Gerente de Projetos', 'modules' => ['tasks', 'users', 'settings']],
        ['key' => 'gabi', 'name' => 'Gabriela Nunes', 'email' => 'gabi@flowtree.test', 'position' => 'Estagiária', 'modules' => ['tasks'], 'active' => false],
    ];

    private const FLAGS = [
        ['name' => 'Bug', 'slug' => 'bug', 'color' => '#ef4444'],
        ['name' => 'Melhoria', 'slug' => 'melhoria', 'color' => '#3b82f6'],
        ['name' => 'Urgente', 'slug' => 'urgente', 'color' => '#f97316'],
        ['name' => 'Bloqueado', 'slug' => 'bloqueado', 'color' => '#64748b'],
        ['name' => 'Documentação', 'slug' => 'documentacao', 'color' => '#14b8a6'],
        ['name' => 'Refatoração', 'slug' => 'refatoracao', 'color' => '#a855f7'],
        ['name' => 'Infra', 'slug' => 'infra', 'color' => '#eab308'],
    ];

    /**
     * Projetos: equipe (keys de USERS), sprints e títulos de cards. Cada projeto
     * tem uma sprint concluída, uma ativa e uma planejada, mais backlog solto.
     */
    private const PROJECTS = [
        [
            'name' => 'FlowTree Demo',
            'description' => 'O próprio FlowTree: board Kanban com sprints, métricas e tempo real.',
            'color' => '#22c55e',
            'owner' => 'admin',
            'members' => ['dev', 'ana', 'bruno', 'carla', 'diego'],
            'cards' => [
                'Configurar ambiente Docker (api, app, mysql, redis)', 'Modelagem do banco: projetos, colunas, sprints e cards',
                'Autenticação JWT com refresh automático', 'Drag and drop de cards entre colunas', 'Limite de WIP por coluna',
                'Endpoint de métricas (throughput e tempo de ciclo)', 'Modal de gerenciamento de sprints', 'Exportar sprint em CSV',
                'Notificações em tempo real com Reverb', 'Presença: quem está no board', 'Tela de métricas com gráficos',
                'Modo escuro no board', 'Popover de prioridade no card', 'Anexos no card', 'Comentários com resposta',
                'Documentar a API no Swagger', 'Testes de movimentação por célula', 'Integração com calendário',
                'Atalhos de teclado no board', 'Arquivar projetos concluídos', 'Filtro por responsável e flag',
            ],
        ],
        [
            'name' => 'App Mobile',
            'description' => 'Aplicativo iOS/Android para clientes acompanharem pedidos e conversarem com o suporte.',
            'color' => '#0ea5e9',
            'owner' => 'felipe',
            'members' => ['ana', 'bruno', 'carla', 'diego', 'elisa'],
            'cards' => [
                'Fluxo de onboarding em 3 passos', 'Login com Google e Apple', 'Push notifications (FCM)', 'Tela de pedidos com status',
                'Chat com o suporte', 'Modo offline para lista de pedidos', 'Biometria para desbloqueio', 'Deep links de campanha',
                'Tela de perfil e endereços', 'Avaliação do pedido com estrelas', 'Publicação na App Store', 'Publicação na Play Store',
                'Crash ao abrir o app sem internet', 'Ícone e splash screen', 'Analytics de funil de compra', 'Tradução para espanhol',
                'Pagamento com Pix', 'Acessibilidade: leitor de tela', 'Refatorar navegação para stack única',
            ],
        ],
        [
            'name' => 'Site Institucional',
            'description' => 'Novo site da empresa com blog, página de carreiras e formulário de contato.',
            'color' => '#a855f7',
            'owner' => 'ana',
            'members' => ['bruno', 'elisa', 'diego', 'gabi'],
            'cards' => [
                'Wireframes da home', 'Design system: cores e tipografia', 'Página inicial', 'Página Sobre nós', 'Blog com categorias',
                'Página de carreiras com vagas', 'Formulário de contato com reCAPTCHA', 'SEO: metadados e sitemap', 'Performance: Lighthouse acima de 90',
                'Migrar posts do WordPress', 'Banner de cookies (LGPD)', 'Integração com newsletter', 'Página 404 personalizada',
                'Imagens quebradas no Safari', 'Deploy no Cloudflare Pages', 'Versão em inglês', 'Depoimentos de clientes',
            ],
        ],
        [
            'name' => 'API de Pagamentos',
            'description' => 'Serviço de cobranças com Pix, cartão e boleto, webhooks e conciliação.',
            'color' => '#f59e0b',
            'owner' => 'carla',
            'members' => ['dev', 'carla', 'diego', 'felipe'],
            'cards' => [
                'Cobrança via Pix com QR Code dinâmico', 'Cobrança com cartão (tokenização)', 'Emissão de boleto', 'Webhooks de confirmação de pagamento',
                'Retentativa de webhook com backoff', 'Conciliação diária com o adquirente', 'Estorno parcial', 'Relatório de recebíveis',
                'Rate limit por cliente', 'Idempotência nas cobranças', 'Auditoria de alterações', 'Rotação de chaves de API',
                'Timeout intermitente no adquirente', 'Sandbox para clientes', 'Documentação OpenAPI', 'Alertas no Grafana',
                'Migrar fila para Redis', 'Assinaturas recorrentes', 'Split de pagamento entre lojistas', 'Cache das taxas de câmbio',
            ],
        ],
        [
            'name' => 'Painel Administrativo',
            'description' => 'Backoffice interno: usuários, permissões, relatórios e configurações da plataforma.',
            'color' => '#ec4899',
            'owner' => 'felipe',
            'members' => ['dev', 'ana', 'bruno', 'elisa'],
            'cards' => [
                'Gestão de usuários e permissões', 'Log de auditoria', 'Dashboard com KPIs', 'Relatório de vendas por período',
                'Exportação para Excel', 'Configurações gerais da plataforma', 'Tema escuro', 'Busca global (cmd+k)',
                'Impersonar usuário para suporte', 'Bloqueio de usuário', 'Tabela com paginação server-side', 'Filtros salvos',
                'Erro 500 ao exportar relatório grande', 'Componentes de formulário reutilizáveis', 'Login com SSO corporativo',
                'Notificações internas', 'Testes E2E com Playwright', 'Upload de logo da empresa',
            ],
        ],
    ];

    private const DESCRIPTIONS = [
        "## Contexto\n%s faz parte da entrega planejada para esta sprint.\n\n## Critérios de aceite\n- Funciona no desktop e no mobile\n- Coberto por testes\n- Documentado",
        "Tarefa: %s.\n\nObservações levantadas na reunião de planejamento. Validar com o time antes de fechar.",
        "### Objetivo\n%s\n\n### Passos\n1. Levantar requisitos\n2. Implementar\n3. Revisar em par\n4. Testar em homologação",
        '%s — ver anexos e comentários para detalhes. Qualquer dúvida, falar com o responsável.',
        null,
    ];

    private const COMMENTS = [
        'Comecei a olhar isso hoje, devo ter novidades amanhã.', 'Precisa de mais detalhes antes de estimar.',
        'Testei em homologação e funcionou. Podemos seguir.', 'Encontrei um efeito colateral na tela de listagem, vou abrir outro card.',
        'Bloqueado aguardando resposta do cliente.', 'Subi o PR para revisão: quem puder olhar, agradeço.',
        'Alinhado com a Ana: prioridade sobe para alta.', 'Vamos deixar para a próxima sprint, o escopo cresceu.',
        'Adicionei o print do erro nos anexos.', 'Revisado e aprovado. Pode mergear.', 'Faltou tratar o estado vazio.',
        'Rodou no Chrome, mas no Firefox o layout quebra.', 'Dúvida: isso vale também para usuários inativos?',
        'Combinado, faço ainda hoje.', 'Merge feito. Fechando quando subir para produção.',
    ];

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, int> */
    private array $flagIds = [];

    public function run(): void
    {
        mt_srand(self::SEED);

        // Dono do projeto "FlowTree Demo": o admin do .env ou, no reset pela tela de
        // configurações (que mantém só quem executou), o primeiro admin existente.
        $this->users['admin'] = User::where('email', config('admin.email'))->first()
            ?? User::where('is_admin', true)->orderBy('id')->firstOrFail();
        $this->seedUsers();
        $this->seedFlags();

        foreach (self::PROJECTS as $spec) {
            if (Project::where('name', $spec['name'])->exists()) {
                continue;
            }
            $this->seedProject($spec);
        }
    }

    private function seedUsers(): void
    {
        $moduleIds = Module::pluck('id', 'slug');

        foreach (self::USERS as $spec) {
            $user = User::firstOrCreate(
                ['email' => $spec['email']],
                [
                    'name' => $spec['name'],
                    'password' => Hash::make('password'),
                    'position' => $spec['position'],
                    'is_admin' => false,
                    'is_active' => $spec['active'] ?? true,
                ],
            );
            $user->modules()->syncWithoutDetaching(
                collect($spec['modules'])->map(fn ($slug) => $moduleIds[$slug])->all(),
            );
            $this->users[$spec['key']] = $user;
        }
    }

    private function seedFlags(): void
    {
        foreach (self::FLAGS as $flag) {
            $this->flagIds[$flag['slug']] = Flag::firstOrCreate(['slug' => $flag['slug']], $flag)->id;
        }
    }

    private function seedProject(array $spec): void
    {
        $owner = $this->users[$spec['owner']];
        $members = collect($spec['members'])->map(fn ($key) => $this->users[$key]);

        $project = Project::create([
            'name' => $spec['name'],
            'description' => $spec['description'],
            'color' => $spec['color'],
            'created_by' => $owner->id,
        ]);

        $project->members()->sync(
            $members->mapWithKeys(fn (User $u) => [$u->id => ['role' => 'member']])
                ->put($owner->id, ['role' => 'owner'])
                ->all(),
        );

        $columns = collect(Project::DEFAULT_COLUMNS)
            ->map(fn ($column) => $project->columns()->create($column))
            ->values();

        $monday = now()->startOfWeek();
        $sprints = [
            'done' => $project->sprints()->create([
                'name' => 'Sprint 1', 'goal' => 'Fundação: infraestrutura, modelagem e primeiras telas.',
                'starts_at' => $monday->copy()->subDays(28)->toDateString(), 'ends_at' => $monday->copy()->subDays(15)->toDateString(),
                'status' => Sprint::STATUS_COMPLETED, 'position' => 0, 'completed_at' => $monday->copy()->subDays(15)->setTime(18, 0),
            ]),
            'active' => $project->sprints()->create([
                'name' => 'Sprint 2', 'goal' => 'Fluxo principal de ponta a ponta, pronto para homologação.',
                'starts_at' => $monday->copy()->subDays(7)->toDateString(), 'ends_at' => $monday->copy()->addDays(6)->toDateString(),
                'status' => Sprint::STATUS_ACTIVE, 'position' => 1,
            ]),
            'planned' => $project->sprints()->create([
                'name' => 'Sprint 3', 'goal' => 'Polimento, métricas e itens de menor prioridade.',
                'starts_at' => $monday->copy()->addDays(7)->toDateString(), 'ends_at' => $monday->copy()->addDays(20)->toDateString(),
                'status' => Sprint::STATUS_PLANNED, 'position' => 2,
            ]),
        ];

        // Distribuição dos títulos: ~30% sprint concluída, ~40% ativa, ~15% planejada, resto backlog.
        $titles = $spec['cards'];
        $total = count($titles);
        $cuts = [
            'done' => (int) round($total * 0.3),
            'active' => (int) round($total * 0.4),
            'planned' => (int) round($total * 0.15),
        ];

        $positions = [];
        $index = 0;
        foreach ($titles as $title) {
            $bucket = match (true) {
                $index < $cuts['done'] => 'done',
                $index < $cuts['done'] + $cuts['active'] => 'active',
                $index < $cuts['done'] + $cuts['active'] + $cuts['planned'] => 'planned',
                default => 'backlog',
            };
            $index++;

            $this->seedCard($project, $columns, $sprints, $members->push($owner)->unique('id'), $bucket, $title, $positions);
        }
    }

    /**
     * @param  Collection<int, BoardColumn>  $columns
     * @param  array<string, Sprint>  $sprints
     * @param  Collection<int, User>  $team
     * @param  array<string, int>  $positions
     */
    private function seedCard(Project $project, $columns, array $sprints, $team, string $bucket, string $title, array &$positions): void
    {
        $sprint = $bucket === 'backlog' ? null : $sprints[$bucket];
        $lastIndex = $columns->count() - 1; // Concluído

        // Coluna atual conforme o cenário da sprint.
        $columnIndex = match ($bucket) {
            // Sprint concluída: quase tudo em Concluído; alguns ficaram para trás.
            'done' => $this->chance(85) ? $lastIndex : mt_rand(1, $lastIndex - 1),
            // Sprint ativa: espalhado pelo fluxo.
            'active' => $this->pick([0, 1, 1, 2, 2, 3, 3, 4, 5, 5]),
            // Planejada: Backlog/Pendente.
            'planned' => $this->pick([0, 0, 0, 1]),
            default => 0,
        };
        $column = $columns[$columnIndex];

        // Linha do tempo: criação → movimentos até a coluna atual.
        $createdAt = match ($bucket) {
            'done' => now()->subDays(mt_rand(26, 30)),
            'active' => now()->subDays(mt_rand(3, 12)),
            'planned' => now()->subDays(mt_rand(1, 6)),
            default => now()->subDays(mt_rand(1, 20)),
        };
        $createdAt = $createdAt->setTime(mt_rand(8, 18), $this->pick([0, 15, 30, 45]));
        $endAt = $bucket === 'done' ? now()->subDays(15) : now()->subHours(mt_rand(1, 20));

        $creator = $this->pick($team->all());
        $assignee = $this->chance(80) ? $this->pick($team->all()) : null;
        $priority = $this->pick(['low', 'low', 'medium', 'medium', 'medium', 'high', 'high', 'urgent']);

        $cell = $column->id.':'.($sprint?->id ?? 'backlog');
        $position = $positions[$cell] ?? 0;
        $positions[$cell] = $position + 1;

        $isDone = $column->is_done;
        $dueDate = match (true) {
            $isDone => $this->chance(60) ? $endAt->copy()->addDays(mt_rand(-2, 3))->toDateString() : null,
            $this->chance(65) => now()->addDays($this->pick([-4, -2, -1, 1, 2, 3, 5, 7, 10, 14]))->toDateString(),
            default => null,
        };

        $card = $project->cards()->create([
            'board_column_id' => $column->id,
            'sprint_id' => $sprint?->id,
            'assignee_id' => $assignee?->id,
            'created_by' => $creator->id,
            'title' => $title,
            'description' => $this->description($title),
            'priority' => $priority,
            'due_date' => $dueDate,
            'position' => $position,
        ]);
        Card::withoutTimestamps(fn () => $card->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save());

        // Flags (0 a 2), sem repetir.
        $flagSlugs = collect(array_keys($this->flagIds))->shuffle()->take($this->pick([0, 0, 1, 1, 1, 2]));
        if ($flagSlugs->isNotEmpty()) {
            $card->flags()->sync($flagSlugs->map(fn ($slug) => $this->flagIds[$slug])->all());
        }

        // Histórico: passa por cada coluna intermediária até a atual, em datas crescentes.
        $this->seedMovements($card, $columns, $columnIndex, $createdAt, $endAt, $assignee ?? $creator);

        // Comentários entre a equipe.
        $this->seedComments($card, $team, $createdAt, $assignee, $creator);

        // Anexos em parte dos cards.
        if ($this->chance(25)) {
            $this->seedAttachment($card, $creator);
        }

        // Notificação de atribuição (lida nas antigas; recentes ficam por ler).
        if ($assignee && $assignee->id !== $creator->id) {
            $this->notify($assignee, CardAssigned::class, [
                'type' => 'card_assigned',
                'card_uuid' => $card->uuid,
                'card_title' => $card->title,
                'project_uuid' => $project->uuid,
                'project_name' => $project->name,
                'actor_uuid' => $creator->uuid,
                'actor_name' => $creator->name,
            ], $createdAt, read: $createdAt->lt(now()->subDays(2)) || $this->chance(50));
        }
    }

    /**
     * @param  Collection<int, BoardColumn>  $columns
     */
    private function seedMovements(Card $card, $columns, int $targetIndex, CarbonInterface $createdAt, CarbonInterface $endAt, User $mover): void
    {
        $card->movements()->create([
            'from_column_id' => null,
            'to_column_id' => $columns[0]->id,
            'user_id' => $card->created_by,
            'created_at' => $createdAt,
        ]);

        if ($targetIndex === 0) {
            return;
        }

        // Datas espaçadas uniformemente entre a criação e a última movimentação.
        $span = max(1, $createdAt->diffInMinutes($endAt));
        $steps = $targetIndex;
        $startedAt = null;
        $completedAt = null;

        for ($i = 1; $i <= $steps; $i++) {
            $at = $createdAt->copy()->addMinutes((int) ($span * $i / $steps));
            $card->movements()->create([
                'from_column_id' => $columns[$i - 1]->id,
                'to_column_id' => $columns[$i]->id,
                'user_id' => $mover->id,
                'created_at' => $at,
            ]);
            $startedAt ??= $at;
            if ($columns[$i]->is_done) {
                $completedAt = $at;
            }
        }

        Card::withoutTimestamps(fn () => $card->forceFill([
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'updated_at' => $completedAt ?? $startedAt,
        ])->save());
    }

    /**
     * @param  Collection<int, User>  $team
     */
    private function seedComments(Card $card, $team, CarbonInterface $createdAt, ?User $assignee, User $creator): void
    {
        $count = $this->pick([0, 0, 1, 1, 2, 2, 3, 4]);
        $at = $createdAt->copy();

        for ($i = 0; $i < $count; $i++) {
            $author = $this->pick($team->all());
            $at = $at->copy()->addHours(mt_rand(2, 30));
            if ($at->isFuture()) {
                break;
            }

            $comment = $card->comments()->create([
                'user_id' => $author->id,
                'body' => $this->pick(self::COMMENTS),
            ]);
            $comment->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

            // Quem comentou avisa o responsável e o criador (menos a si mesmo).
            foreach (collect([$assignee, $creator])->filter()->unique('id') as $target) {
                if ($target->id === $author->id) {
                    continue;
                }
                $this->notify($target, CardCommented::class, [
                    'type' => 'card_commented',
                    'card_uuid' => $card->uuid,
                    'card_title' => $card->title,
                    'project_uuid' => $card->project->uuid,
                    'project_name' => $card->project->name,
                    'actor_uuid' => $author->uuid,
                    'actor_name' => $author->name,
                    'comment_excerpt' => Str::limit($comment->body, 80),
                ], $at, read: $at->lt(now()->subDay()) || $this->chance(40));
            }
        }
    }

    /** Gera um arquivo real no disco público e o registra como anexo do card. */
    private function seedAttachment(Card $card, User $user): void
    {
        $kind = $this->pick(['md', 'csv', 'txt', 'png']);
        $dir = "attachments/{$card->id}";
        $disk = 'public';

        [$filename, $content, $mime] = match ($kind) {
            'md' => ['briefing.md', "# {$card->title}\n\nBriefing gerado pelo seeder.\n\n- Escopo\n- Prazo\n- Riscos\n", 'text/markdown'],
            'csv' => ['dados.csv', "id;item;quantidade\n1;Exemplo A;10\n2;Exemplo B;25\n3;Exemplo C;7\n", 'text/csv'],
            'txt' => ['notas.txt', "Notas da reunião sobre \"{$card->title}\".\n\nDecisões:\n- Seguir com a abordagem proposta.\n- Revisar na próxima sprint.\n", 'text/plain'],
            default => ['print.png', $this->png($card->title), 'image/png'],
        };

        $path = "{$dir}/".Str::uuid().'.'.$kind;
        Storage::disk($disk)->put($path, $content);

        $card->attachments()->create([
            'user_id' => $user->id,
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
            'mime_type' => $mime,
            'size' => strlen($content),
        ]);
    }

    /** PNG simples (bloco colorido com texto) para servir de "print" de exemplo. */
    private function png(string $text): string
    {
        $img = imagecreatetruecolor(640, 360);
        $bg = imagecolorallocate($img, 0x3F, 0x66, 0x50);
        $fg = imagecolorallocate($img, 0xF6, 0xF3, 0xEC);
        imagefilledrectangle($img, 0, 0, 640, 360, $bg);
        imagestring($img, 5, 24, 24, 'FlowTree - print de exemplo', $fg);
        imagestring($img, 3, 24, 60, substr($text, 0, 70), $fg);
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    /** Insere a notificação direto no banco (sem disparar broadcast durante o seed). */
    private function notify(User $user, string $type, array $data, CarbonInterface $at, bool $read): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => $data,
            'read_at' => $read ? $at->copy()->addHours(mt_rand(1, 12)) : null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function description(string $title): ?string
    {
        $template = $this->pick(self::DESCRIPTIONS);

        return $template === null ? null : sprintf($template, $title);
    }

    private function chance(int $percent): bool
    {
        return mt_rand(1, 100) <= $percent;
    }

    private function pick(array $options): mixed
    {
        return $options[mt_rand(0, count($options) - 1)];
    }
}
