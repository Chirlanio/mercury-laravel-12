# Modulo 2B: Treinamentos & Capacitacao (Training Platform)

**Status:** Pendente
**Fase:** 2B
**Prioridade:** MEDIA — Desenvolvimento RH
**Estimativa:** ~120 arquivos novos (5 submodulos)
**Referencia v1:** `C:\wamp64\www\mercury\app\adms\Controllers\Training*.php`, `TrainingCourses*.php`, `TrainingQuizzes*.php`, `FillExperienceEvaluation.php`

---

## 1. Visao Geral

Plataforma completa de capacitacao corporativa com 5 submodulos integrados:

| # | Submodulo | Descricao | Tabelas |
|---|-----------|-----------|---------|
| A | **Training Events** | Eventos presenciais/hibridos com QR code check-in, avaliacao e certificados | 7 |
| B | **Training Content** | Biblioteca de conteudos (video, audio, documento, link, texto) com categorias | 2 |
| C | **Training Courses** | Trilhas de aprendizagem com inscricoes, progresso, visibilidade e certificados | 4 |
| D | **Training Quizzes** | Questionarios com scoring, tentativas, timer e tipos de pergunta | 5 |
| E | **Experience Tracker (APE)** | Avaliacao de periodo de experiencia (45/90 dias) gestor + colaborador | 4 |

**Total: 22 tabelas, ~120 arquivos**

---

## 2. Submodulo A: Training Events (Eventos Presenciais)

### State Machine
```
Rascunho (draft) -> Publicado (published) -> Em Andamento (in_progress) -> Concluido (completed)
Qualquer (exceto completed) -> Cancelado (cancelled)
```

### Tabelas (7)

#### training_statuses (config/seed)
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| name | varchar(50) | Nome do status |
| color | varchar(20) | Cor para StatusBadge |
| description | varchar(255) | Descricao |

**Seed:** 1=Rascunho(secondary), 2=Publicado(primary), 3=Em Andamento(warning), 4=Concluido(success), 5=Cancelado(danger)

#### training_facilitators
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| name | varchar(255) | Nome completo |
| email | varchar(255) | Email (nullable) |
| phone | varchar(20) | Telefone (nullable) |
| bio | text | Biografia (nullable) |
| photo_path | varchar(255) | Foto (nullable) |
| external | boolean | 0=Interno, 1=Externo |
| employee_id | FK nullable | Referencia ao Employee se interno |
| is_active | boolean | Status ativo/inativo |
| created_by_user_id | FK | Quem criou |
| timestamps + soft_deletes | | |

#### training_subjects
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| name | varchar(255) | Nome do assunto |
| description | text | Descricao (nullable) |
| is_active | boolean | Status ativo/inativo |
| created_by_user_id | FK | Quem criou |
| timestamps + soft_deletes | | |

#### certificate_templates
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| name | varchar(255) | Nome do template |
| html_template | longtext | HTML com placeholders: {{participant_name}}, {{training_title}}, {{training_date}}, {{duration}}, {{subject}}, {{facilitator_name}}, {{certificate_code}} |
| background_image | varchar(255) | Imagem de fundo (nullable) |
| is_default | boolean | Template padrao |
| is_active | boolean | Status ativo/inativo |
| created_by_user_id | FK | Quem criou |
| timestamps + soft_deletes | | |

**Seed:** 1 template padrao (formal com borda dupla, logo Grupo Meia Sola)

#### trainings
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| hash_id | uuid unique | UUID para URLs publicas |
| title | varchar(255) | Titulo do evento |
| description | text | Descricao (nullable, HTML permitido) |
| image_path | varchar(255) | Banner (nullable) |
| event_date | date | Data do evento |
| start_time | time | Hora inicio |
| end_time | time | Hora fim |
| duration_minutes | int | Calculado (end - start) |
| location | varchar(255) | Local (nullable) |
| max_participants | int | Limite de vagas (nullable = ilimitado) |
| facilitator_id | FK | Facilitador responsavel |
| subject_id | FK | Assunto/tema |
| status | varchar(20) | draft/published/in_progress/completed/cancelled |
| attendance_qrcode_token | varchar(64) unique | Token QR presenca |
| evaluation_qrcode_token | varchar(64) unique | Token QR avaliacao |
| allow_late_attendance | boolean | Permitir atrasados |
| attendance_grace_minutes | int default 15 | Tolerancia em minutos |
| certificate_template_id | FK nullable | Template de certificado |
| evaluation_enabled | boolean default true | Habilitar avaliacao |
| created_by_user_id | FK | Quem criou |
| updated_by_user_id | FK nullable | Quem atualizou |
| timestamps + soft_deletes | | |

#### training_participants
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| training_id | FK cascade | Treinamento |
| employee_id | FK nullable | Funcionario interno (nullable) |
| google_email | varchar(255) | Email Google |
| google_name | varchar(255) | Nome Google |
| attendance_time | datetime | Hora do check-in |
| ip_address | varchar(45) | IP do check-in |
| is_late | boolean | Chegou atrasado |
| certificate_generated | boolean | Certificado gerado |
| certificate_path | varchar(255) | Caminho do PDF |
| certificate_sent_at | datetime | Data envio email |
| timestamps | | |

**Unique:** (training_id, google_email)

#### training_evaluations
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| training_id | FK cascade | Treinamento |
| participant_id | FK cascade | Participante |
| rating | tinyint | 1 a 5 estrelas |
| comment | text | Comentario (nullable) |
| timestamps | | |

**Unique:** (training_id, participant_id)
**Check:** rating BETWEEN 1 AND 5

### Services (2)

#### TrainingQRCodeService
- `generateQRCode(string $token, string $type, int $size = 300): string` — Gera QR code como base64
- `getPublicUrl(string $token, string $type): string` — URL publica para scan
- Cores: presenca=verde(#28a745), avaliacao=azul(#007bff)
- Usa: SimpleSoftwareIO/simple-qrcode ou equivalente Laravel

#### TrainingCertificateService
- `generate(Training $training, TrainingParticipant $participant): string` — Gera PDF via dompdf
- `generateBulk(Training $training): array` — Gera para todos participantes
- `sendByEmail(TrainingParticipant $participant): bool` — Envia certificado por email
- Templates predefinidos: modern (gradiente purple/blue), formal (bordas elaboradas), minimalist (clean), customizado (HTML do usuario)
- Placeholders: {{participant_name}}, {{training_title}}, {{training_date}}, {{duration}}, {{subject}}, {{facilitator_name}}, {{certificate_code}}

### Controller: TrainingEventController
| Metodo | Rota | Descricao |
|--------|------|-----------|
| index | GET /trainings | Listagem com filtros e estatisticas |
| store | POST /trainings | Criar evento |
| show | GET /trainings/{id} | Detalhes (JSON) |
| edit | GET /trainings/{id}/edit | Dados para edicao (JSON) |
| update | PUT /trainings/{id} | Atualizar evento |
| destroy | DELETE /trainings/{id} | Excluir (soft delete) |
| transition | POST /trainings/{id}/transition | Transicao de status |
| qrCodes | GET /trainings/{id}/qr-codes | Gerar/exibir QR codes (JSON) |
| generateCertificates | POST /trainings/{id}/certificates | Gerar certificados em lote |
| sendCertificates | POST /trainings/{id}/certificates/send | Enviar por email |
| statistics | GET /trainings/statistics | KPIs gerais |

### Frontend (3 arquivos)
- `Pages/Trainings/Index.jsx` — Listagem, filtros, stats, tabs (Eventos / Cursos / Conteudos / Quizzes)
- `Components/Trainings/EventDetailModal.jsx` — Detalhes do evento com participantes, QR, avaliacoes
- `Components/Trainings/EventFormModal.jsx` — Criar/editar evento (StandardModal com onSubmit)

### Estatisticas (StatisticsGrid)
- Total de treinamentos / Por status (5 cards)
- Proximos eventos (publicados, data >= hoje)
- Treinamentos este mes / Treinamentos hoje
- Total participantes / Media por evento
- Avaliacao media / Bem avaliados (>= 4 estrelas)
- Facilitadores ativos / Assuntos ativos

---

## 3. Submodulo B: Training Content (Biblioteca de Conteudos)

### Tabelas (2)

#### training_content_categories
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| name | varchar(100) | Nome da categoria |
| icon | varchar(50) | Heroicon name |
| color | varchar(20) | Cor para badge |
| is_active | boolean | Status ativo/inativo |
| created_by_user_id | FK | Quem criou |
| timestamps | | |

**Seed:** Onboarding(info), Produto(primary), Processo(warning), Compliance(danger), Soft Skills(success), Tecnico(secondary)

#### training_contents
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| hash_id | uuid unique | UUID para URLs |
| title | varchar(255) | Titulo |
| description | text | Descricao (nullable) |
| content_type | varchar(20) | video/audio/document/link/text |
| file_path | varchar(500) | Caminho do arquivo (nullable) |
| file_name | varchar(255) | Nome original (nullable) |
| file_size | bigint | Tamanho em bytes (nullable) |
| file_mime_type | varchar(100) | MIME type (nullable) |
| external_url | varchar(500) | URL externa YouTube/Vimeo (nullable) |
| text_content | longtext | Conteudo texto/HTML (nullable) |
| duration_seconds | int | Duracao video/audio (nullable) |
| thumbnail_path | varchar(255) | Miniatura (nullable) |
| category_id | FK nullable | Categoria |
| is_active | boolean | Status ativo/inativo |
| created_by_user_id | FK | Quem criou |
| updated_by_user_id | FK nullable | Quem atualizou |
| timestamps + soft_deletes | | |

### Service: TrainingContentUploadService
- `upload(UploadedFile $file, string $contentType): array` — Upload com validacao MIME real (finfo)
- `uploadThumbnail(UploadedFile $file): string` — Upload miniatura (max 5MB, jpg/png/webp)
- `delete(string $filePath): bool` — Remove arquivo
- Limites: video=500MB, audio=100MB, documento=50MB, thumbnail=5MB
- Extensoes: video(mp4,webm,ogg), audio(mp3,wav,ogg,m4a), document(pdf,ppt,pptx,doc,docx)

### Controller: TrainingContentController
| Metodo | Rota | Descricao |
|--------|------|-----------|
| index | GET /training-contents | Listagem com filtros por tipo/categoria |
| store | POST /training-contents | Criar conteudo com upload |
| show | GET /training-contents/{id} | Detalhes (JSON) |
| update | PUT /training-contents/{id} | Atualizar conteudo |
| destroy | DELETE /training-contents/{id} | Excluir (soft delete) |

### Frontend (2 arquivos)
- `Components/Trainings/ContentDetailModal.jsx` — Detalhes do conteudo com preview
- `Components/Trainings/ContentFormModal.jsx` — Criar/editar conteudo com upload

---

## 4. Submodulo C: Training Courses (Trilhas de Aprendizagem)

### Tabelas (4)

#### training_courses
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| hash_id | uuid unique | UUID para URLs |
| title | varchar(255) | Titulo do curso |
| description | text | Descricao (nullable) |
| thumbnail_path | varchar(255) | Miniatura (nullable) |
| subject_id | FK nullable | Assunto (reusa training_subjects) |
| facilitator_id | FK nullable | Facilitador (reusa training_facilitators) |
| visibility | varchar(10) | public/private |
| status | varchar(20) | draft/published/archived |
| requires_sequential | boolean default false | Conteudos em ordem obrigatoria |
| certificate_on_completion | boolean default false | Gerar certificado ao concluir |
| certificate_template_id | FK nullable | Template certificado |
| estimated_duration_minutes | int nullable | Duracao estimada total |
| published_at | datetime nullable | Data publicacao |
| created_by_user_id | FK | Quem criou |
| updated_by_user_id | FK nullable | Quem atualizou |
| timestamps + soft_deletes | | |

#### training_course_contents (pivot)
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| course_id | FK cascade | Curso |
| content_id | FK cascade | Conteudo |
| sort_order | int default 0 | Ordem de exibicao |
| is_required | boolean default true | Obrigatorio para certificado |

**Unique:** (course_id, content_id)

#### training_course_enrollments
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| course_id | FK cascade | Curso |
| user_id | FK nullable | Usuario interno (nullable se externo) |
| employee_id | FK nullable | Funcionario (nullable) |
| status | varchar(20) | enrolled/in_progress/completed/dropped |
| enrolled_at | datetime | Data inscricao |
| completed_at | datetime nullable | Data conclusao |
| completion_percent | decimal(5,2) default 0 | % concluido |
| certificate_generated | boolean default false | Certificado gerado |
| certificate_path | varchar(255) nullable | Caminho PDF |
| timestamps | | |

**Unique:** (course_id, user_id)

#### training_course_visibility
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| course_id | FK cascade | Curso |
| target_type | varchar(20) | store/role/user |
| target_id | varchar(20) | ID do alvo |
| timestamps | | |

**Unique:** (course_id, target_type, target_id)

### Services (3)

#### TrainingEnrollmentService
- `enroll(Course $course, User $user): Enrollment` — Inscrever usuario (idempotente)
- `hasAccess(Course $course, User $user): bool` — Verificar acesso (public=todos, private=visibility rules)
- `isContentUnlocked(Course $course, Content $content, User $user): bool` — Verificar sequencial lock

#### TrainingProgressService
- `updateProgress(Content $content, ?Course $course, User $user, array $data): array` — Atualizar progresso
- `markComplete(Content $content, ?Course $course, User $user): array` — Marcar conteudo concluido
- `recalculateCourseProgress(Course $course, User $user): bool` — Recalcular % do curso
- Thresholds auto-complete: video/audio >= 90%, PDF >= 95%, documento >= 50%, texto >= 90%, link >= 50%

#### TrainingCourseCompletionService
- `processCompletion(Course $course, User $user): bool` — Processar conclusao (gerar certificado + notificar)
- `resendCertificate(Enrollment $enrollment): bool` — Reenviar certificado

### Controller: TrainingCourseController
| Metodo | Rota | Descricao |
|--------|------|-----------|
| index | GET /training-courses | Listagem com filtros |
| store | POST /training-courses | Criar curso |
| show | GET /training-courses/{id} | Detalhes com conteudos (JSON) |
| update | PUT /training-courses/{id} | Atualizar curso |
| destroy | DELETE /training-courses/{id} | Excluir (soft delete) |
| manageContents | POST /training-courses/{id}/contents | Gerenciar conteudos (order, required) |
| manageVisibility | POST /training-courses/{id}/visibility | Gerenciar visibilidade |
| enroll | POST /training-courses/{id}/enroll | Inscrever usuario |
| myTrainings | GET /my-trainings | Cursos do usuario logado |
| watchContent | GET /training-courses/{id}/watch/{contentId} | Player de conteudo |
| saveProgress | POST /training-contents/{id}/progress | Salvar progresso (AJAX, a cada 30s) |
| markComplete | POST /training-contents/{id}/complete | Marcar conteudo completo |
| reports | GET /training-reports | Relatorios (overview, by-employee, by-store, by-course) |
| exportReport | GET /training-reports/export | Exportar relatorio Excel |

### Progresso por Conteudo (tracking inline, nao tabela separada)
- Armazenado em `training_content_progress` (tabela complementar)

#### training_content_progress
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| user_id | FK nullable | Usuario |
| content_id | FK cascade | Conteudo |
| course_id | FK nullable | Curso via qual acessou |
| status | varchar(20) | not_started/in_progress/completed |
| progress_percent | decimal(5,2) default 0 | % concluido |
| started_at | datetime nullable | Inicio |
| completed_at | datetime nullable | Conclusao |
| last_position_seconds | int default 0 | Posicao no video/audio |
| total_time_spent_seconds | int default 0 | Tempo total gasto |
| views_count | int default 0 | Vezes acessado |
| last_accessed_at | datetime nullable | Ultimo acesso |
| timestamps | | |

**Unique:** (user_id, content_id, course_id)

### Frontend (5 arquivos)
- `Components/Trainings/CourseDetailModal.jsx` — Detalhes do curso com lista de conteudos
- `Components/Trainings/CourseFormModal.jsx` — Criar/editar curso com gerenciamento de conteudos
- `Pages/Trainings/MyTrainings.jsx` — Portal do aluno (cursos inscritos, progresso, certificados)
- `Pages/Trainings/WatchContent.jsx` — Player de conteudo (video/audio/documento/texto)
- `Pages/Trainings/Reports.jsx` — Relatorios com filtros e exportacao

### Relatorios
| Relatorio | Metricas |
|-----------|----------|
| Overview | Total cursos, publicados, inscricoes, conclusoes, taxa conclusao, horas, conteudos ativos |
| Por Funcionario | Nome, loja, inscricoes, conclusoes, em andamento, horas, certificados |
| Por Loja | Loja, funcionarios treinados, inscricoes, conclusoes, taxa %, horas |
| Por Curso | Curso, inscritos, concluidos, taxa %, desistencias, qtde conteudos |

---

## 5. Submodulo D: Training Quizzes (Questionarios)

### Tabelas (5)

#### training_quizzes
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| content_id | FK nullable cascade | Vinculado a um conteudo |
| course_id | FK nullable cascade | Ou vinculado a um curso |
| title | varchar(255) | Titulo |
| description | text nullable | Descricao |
| passing_score | int default 70 | Nota minima (%) |
| max_attempts | int nullable | Max tentativas (null=ilimitado) |
| show_answers | boolean default false | Mostrar respostas apos tentativa |
| time_limit_minutes | int nullable | Limite tempo (null=sem limite) |
| is_active | boolean default true | Status |
| created_by_user_id | FK | Quem criou |
| timestamps + soft_deletes | | |

#### training_quiz_questions
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| quiz_id | FK cascade | Quiz |
| question_text | text | Texto da pergunta |
| question_type | varchar(20) | single/multiple/boolean |
| sort_order | int default 0 | Ordem |
| points | int default 1 | Pontos |
| explanation | text nullable | Explicacao (exibida se show_answers) |

#### training_quiz_options
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| question_id | FK cascade | Pergunta |
| option_text | varchar(500) | Texto da opcao |
| is_correct | boolean default false | Resposta correta |
| sort_order | int default 0 | Ordem |

#### training_quiz_attempts
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| quiz_id | FK cascade | Quiz |
| user_id | FK nullable | Usuario interno |
| score | decimal(5,2) default 0 | Nota (%) |
| total_points | int default 0 | Total de pontos possiveis |
| earned_points | int default 0 | Pontos obtidos |
| passed | boolean default false | Aprovado |
| attempt_number | int default 1 | Numero da tentativa |
| started_at | datetime | Inicio |
| completed_at | datetime nullable | Fim |
| timestamps | | |

#### training_quiz_responses
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| attempt_id | FK cascade | Tentativa |
| question_id | FK cascade | Pergunta |
| selected_options | json | Array de IDs selecionados |
| is_correct | boolean default false | Acertou |
| points_earned | int default 0 | Pontos ganhos |

### Service: TrainingQuizService
- `startAttempt(Quiz $quiz, User $user): array` — Iniciar tentativa (valida limite, carrega perguntas sem respostas)
- `submitAttempt(QuizAttempt $attempt, array $answers): array` — Submeter respostas e calcular score
- `getAttemptReview(QuizAttempt $attempt): array` — Revisao da tentativa (respeita show_answers)
- Se aprovado e vinculado a conteudo/curso: marca conteudo completo e recalcula progresso

### Controller: TrainingQuizController
| Metodo | Rota | Descricao |
|--------|------|-----------|
| index | GET /training-quizzes | Listagem |
| store | POST /training-quizzes | Criar quiz com perguntas |
| show | GET /training-quizzes/{id} | Detalhes (JSON) |
| update | PUT /training-quizzes/{id} | Atualizar quiz + perguntas |
| destroy | DELETE /training-quizzes/{id} | Excluir (soft delete) |
| start | POST /training-quizzes/{id}/start | Iniciar tentativa |
| submit | POST /training-quiz-attempts/{id}/submit | Submeter respostas |
| review | GET /training-quiz-attempts/{id}/review | Revisao da tentativa |

### Frontend (3 arquivos)
- `Components/Trainings/QuizDetailModal.jsx` — Detalhes do quiz com estatisticas
- `Components/Trainings/QuizFormModal.jsx` — Criar/editar quiz com builder de perguntas
- `Pages/Trainings/TakeQuiz.jsx` — Interface de quiz (timer, navegacao, submissao)

---

## 6. Submodulo E: Experience Tracker (APE)

### Tabelas (4)

#### experience_evaluations
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| employee_id | FK | Funcionario avaliado |
| manager_id | FK | Gestor avaliador |
| store_id | FK | Loja |
| milestone | varchar(5) | 45 ou 90 (dias) |
| date_admission | date | Data admissao |
| milestone_date | date | Data marco |
| manager_status | varchar(20) default pending | pending/completed |
| employee_status | varchar(20) default pending | pending/completed |
| manager_completed_at | datetime nullable | Gestor completou |
| employee_completed_at | datetime nullable | Colaborador completou |
| employee_token | varchar(64) unique | Token para form publico |
| recommendation | varchar(5) nullable | yes/no (apenas 90 dias) |
| timestamps | | |

**Unique:** (employee_id, milestone)

#### experience_questions
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| milestone | varchar(5) | 45 ou 90 |
| form_type | varchar(10) | employee ou manager |
| question_order | tinyint | Ordem |
| question_text | varchar(500) | Texto |
| question_type | varchar(10) | rating/text/yes_no |
| is_required | boolean default true | Obrigatoria |
| is_active | boolean default true | Ativa |

**Seed:** 7 perguntas gestor 45d, 6 perguntas colaborador 45d, 7 perguntas gestor 90d, 6 perguntas colaborador 90d (26 total)

#### experience_responses
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| evaluation_id | FK cascade | Avaliacao |
| question_id | FK | Pergunta |
| form_type | varchar(10) | employee ou manager |
| response_text | text nullable | Resposta texto |
| rating_value | tinyint nullable | Nota (1-5) |
| yes_no_value | boolean nullable | Sim/nao |
| timestamps | | |

**Unique:** (evaluation_id, question_id, form_type)

#### experience_notifications
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | PK | |
| evaluation_id | FK cascade | Avaliacao |
| notification_type | varchar(20) | created/reminder_5d/reminder_due/overdue |
| recipient_type | varchar(10) | employee ou manager |
| sent_at | datetime | Data envio |

**Unique:** (evaluation_id, notification_type, recipient_type)

### Controller: ExperienceTrackerController
| Metodo | Rota | Descricao |
|--------|------|-----------|
| index | GET /experience-tracker | Listagem avaliacoes com filtros |
| store | POST /experience-tracker | Criar avaliacao (manual ou automatica) |
| show | GET /experience-tracker/{id} | Detalhes com respostas (JSON) |
| fillManager | POST /experience-tracker/{id}/manager | Salvar respostas do gestor |
| statistics | GET /experience-tracker/statistics | KPIs e dashboards |
| compliance | GET /experience-tracker/compliance | Matriz compliance por loja |
| evolution | GET /experience-tracker/evolution | Evolucao 45 -> 90 dias |
| management | GET /experience-tracker/management | Percepcao gestao (avaliacao colaborador) |
| hiring | GET /experience-tracker/hiring | Recomendacoes efetivacao 90 dias |
| ranking | GET /experience-tracker/ranking | Ranking por loja |

### Rota Publica (sem autenticacao Mercury)
| Metodo | Rota | Descricao |
|--------|------|-----------|
| fillEmployee | GET /public/experience/{token} | Formulario publico do colaborador |
| submitEmployee | POST /public/experience/{token} | Submeter respostas colaborador |

### Frontend (4 arquivos)
- `Pages/ExperienceTracker/Index.jsx` — Listagem com filtros, stats, tabs (Avaliacoes / Compliance / Evolucao)
- `Components/ExperienceTracker/EvaluationDetailModal.jsx` — Detalhes com respostas gestor + colaborador
- `Components/ExperienceTracker/EvaluationFormModal.jsx` — Criar avaliacao + formulario gestor
- `Pages/ExperienceTracker/PublicForm.jsx` — Formulario publico (colaborador via token)

### Estatisticas (StatisticsGrid)
- Pendentes total / Proximo do prazo (5 dias) / Vencidas / Concluidas este mes
- Taxa preenchimento por loja (compliance matrix)
- Evolucao media 45 -> 90 dias
- Percepcao de gestao por loja (media avaliacoes colaboradores)
- Taxa efetivacao 90 dias / Ranking por loja

---

## 7. Permissions (14)

```php
// Training Events
VIEW_TRAININGS = 'trainings.view'
CREATE_TRAININGS = 'trainings.create'
EDIT_TRAININGS = 'trainings.edit'
DELETE_TRAININGS = 'trainings.delete'
MANAGE_TRAINING_ATTENDANCE = 'trainings.manage_attendance'

// Training Courses & Content
VIEW_TRAINING_COURSES = 'training_courses.view'
CREATE_TRAINING_COURSES = 'training_courses.create'
EDIT_TRAINING_COURSES = 'training_courses.edit'
DELETE_TRAINING_COURSES = 'training_courses.delete'
MANAGE_TRAINING_CONTENT = 'training_content.manage'

// Training Quizzes
MANAGE_TRAINING_QUIZZES = 'training_quizzes.manage'

// Experience Tracker
VIEW_EXPERIENCE_TRACKER = 'experience_tracker.view'
MANAGE_EXPERIENCE_TRACKER = 'experience_tracker.manage'
FILL_EXPERIENCE_EVALUATION = 'experience_tracker.fill'
```

### Distribuicao por Role

| Permissao | SUPER_ADMIN | ADMIN | SUPPORT | USER |
|-----------|:-----------:|:-----:|:-------:|:----:|
| VIEW_TRAININGS | x | x | x | x |
| CREATE_TRAININGS | x | x | | |
| EDIT_TRAININGS | x | x | | |
| DELETE_TRAININGS | x | x | | |
| MANAGE_TRAINING_ATTENDANCE | x | x | x | |
| VIEW_TRAINING_COURSES | x | x | x | x |
| CREATE_TRAINING_COURSES | x | x | | |
| EDIT_TRAINING_COURSES | x | x | | |
| DELETE_TRAINING_COURSES | x | x | | |
| MANAGE_TRAINING_CONTENT | x | x | | |
| MANAGE_TRAINING_QUIZZES | x | x | | |
| VIEW_EXPERIENCE_TRACKER | x | x | x | |
| MANAGE_EXPERIENCE_TRACKER | x | x | | |
| FILL_EXPERIENCE_EVALUATION | x | x | x | |

---

## 8. Modules Config (config/modules.php)

```php
'training' => [
    'name' => 'Treinamentos',
    'description' => 'Gestao de treinamentos, cursos, conteudos e quizzes',
    'routes' => ['trainings.*', 'training-courses.*', 'training-contents.*', 'training-quizzes.*', 'my-trainings.*'],
    'icon' => 'AcademicCapIcon',
    'dependencies' => ['employees', 'stores'],
],
'experience-tracker' => [
    'name' => 'Avaliacao de Experiencia',
    'description' => 'Acompanhamento do periodo de experiencia (45/90 dias)',
    'routes' => ['experience-tracker.*'],
    'icon' => 'ClipboardDocumentCheckIcon',
    'dependencies' => ['employees', 'stores'],
],
```

---

## 9. Fases de Implementacao

| Fase | Submodulo | Descricao | Arquivos |
|------|-----------|-----------|----------|
| 2B.1 | **Training Events** | Eventos presenciais com QR, avaliacoes, certificados + tabelas base (facilitators, subjects, templates) | ~30 |
| 2B.2 | **Training Content** | Biblioteca de conteudos com upload e categorias | ~15 |
| 2B.3 | **Training Courses** | Trilhas, inscricoes, progresso, visibilidade, relatorios | ~25 |
| 2B.4 | **Training Quizzes** | Questionarios, tentativas, scoring | ~18 |
| 2B.5 | **Experience Tracker** | APE 45/90 dias, form publico, compliance, estatisticas | ~25 |

**Ordem de implementacao:** 2B.1 -> 2B.2 -> 2B.3 -> 2B.4 -> 2B.5

Justificativa: Events estabelece as tabelas base (facilitators, subjects, templates) que Courses reutiliza. Content e prerequisito para Courses e Quizzes. APE e independente mas aproveita patterns estabelecidos.

---

## 10. Decisoes Arquiteturais

### Adaptacoes v1 -> Laravel

| v1 | Laravel |
|----|---------|
| status_id FK -> adms_training_statuses | status varchar com constantes no Model (padrao do projeto) |
| Google OAuth (dual-identity) | Removido — apenas usuarios internos do Mercury (User/Employee) |
| adms_usuarios FK | user_id FK -> users (padrao Laravel) |
| employee_id via manual link | employee_id FK direto (sistema tem Employee model) |
| QR code com Endroid lib | SimpleSoftwareIO/simple-qrcode (padrao Laravel) ou equivalente |
| Session-based rate limiting | Laravel rate limiting middleware |
| Manual CSRF tokens | Laravel CSRF nativo |
| FontAwesome icons | Heroicons React (padrao do projeto) |
| jQuery AJAX | Inertia router.post/get (padrao do projeto) |
| Custom pagination | DataTable com paginacao integrada |
| Custom search filters | Request query params + Eloquent scopes |
| File upload manual | Laravel Storage + validation rules |
| Escola Digital (placeholder) | Substituida pelo submodulo Training Courses |

### Nota sobre Dual-Identity (Google Users)
A v1 suportava usuarios externos via Google OAuth para participacao em treinamentos. Na versao Laravel, **todos os participantes sao usuarios ou funcionarios do sistema**. O QR code check-in para eventos registra o employee_id. Se futuramente for necessario suporte a externos, a arquitetura permite adicionar campos google_* nas tabelas de participantes/enrollments.

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
**Ultima atualizacao:** 2026-04-10
