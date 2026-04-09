# Modulo 2B: Treinamentos (Training)

**Status:** Pendente
**Fase:** 2B
**Prioridade:** MEDIA — Desenvolvimento RH
**Estimativa:** ~24 arquivos novos
**Referencia v1:** `C:\wamp64\www\mercury\app\adms\Controllers\Training.php`

---

## 1. Visao Geral

Gestao de treinamentos presenciais, online e hibridos com check-in via QR code, sistema de avaliacao (1-5 estrelas), geracao de certificados PDF e controle de facilitadores/assuntos.

## 2. State Machine
```
Rascunho (draft) → Publicado (published) → Em Andamento (in_progress) → Concluido (completed)
Qualquer (exceto completed) → Cancelado (cancelled)
```

## 3. Arquivos a Criar

### Migrations (7)
create_trainings, training_subjects, training_facilitators, training_participants, training_evaluations, training_certificate_templates, training_attendance_logs

### Models (7)
Training, TrainingSubject, TrainingFacilitator, TrainingParticipant, TrainingEvaluation, TrainingCertificateTemplate, TrainingAttendanceLog

### Services (2)
TrainingQRCodeService (gera QR + valida check-in), TrainingCertificateService (PDF via dompdf)

### Controller (1), Frontend (5), Export (1), Tests (1)

## 4. Permissions (5)
VIEW_TRAININGS, CREATE_TRAININGS, EDIT_TRAININGS, DELETE_TRAININGS, MANAGE_TRAINING_ATTENDANCE

## 5. Features Chave
- QR code unico por sessao (hash UUID)
- Check-in: QR scan ou manual
- Avaliacao pos-treinamento (1-5 estrelas + comentario)
- Certificado PDF gerado por template HTML configuravel
- Facilitadores internos (employee_id) e externos

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
