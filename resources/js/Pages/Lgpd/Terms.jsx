import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Terms({ termsVersion, hasAccepted, tenant }) {
    const [agreed, setAgreed] = useState(false);
    const [processing, setProcessing] = useState(false);

    const handleAccept = () => {
        setProcessing(true);
        router.post('/terms/accept', {}, {
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <Head title="Termos de Uso" />

            <div className="min-h-screen bg-gray-50 py-12 px-4">
                <div className="max-w-3xl mx-auto">
                    <div className="bg-white rounded-lg shadow-lg overflow-hidden">
                        <div className="px-8 py-6 bg-indigo-900 text-white">
                            <h1 className="text-2xl font-bold">Termos de Uso e Política de Privacidade</h1>
                            <p className="mt-1 text-indigo-200 text-sm">
                                {tenant?.name || 'Mercury'} &mdash; Versão {termsVersion}
                            </p>
                        </div>

                        <div className="px-8 py-6 max-h-[60vh] overflow-y-auto prose prose-sm prose-gray">
                            <h2>1. Termos de Uso</h2>
                            <p>
                                Ao utilizar a plataforma Mercury, você concorda com os seguintes termos e condições.
                                Estes termos regem o uso do sistema de gestão empresarial fornecido como serviço (SaaS).
                            </p>

                            <h3>1.1 Aceitação dos Termos</h3>
                            <p>
                                O uso da plataforma está condicionado à aceitação integral destes Termos de Uso.
                                Ao acessar ou utilizar o sistema, você declara ter lido, compreendido e concordado
                                com todas as disposições aqui contidas.
                            </p>

                            <h3>1.2 Uso Adequado</h3>
                            <p>
                                O usuário compromete-se a utilizar a plataforma exclusivamente para fins legítimos
                                de gestão empresarial, não sendo permitido o uso para atividades ilegais,
                                armazenamento de conteúdo ilícito ou qualquer finalidade que viole a legislação vigente.
                            </p>

                            <h3>1.3 Responsabilidade pelos Dados</h3>
                            <p>
                                O usuário é responsável pela veracidade e atualização dos dados inseridos na plataforma.
                                A empresa contratante é a controladora dos dados pessoais de seus funcionários e clientes
                                inseridos no sistema.
                            </p>

                            <h2>2. Política de Privacidade (LGPD)</h2>
                            <p>
                                Em conformidade com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018),
                                informamos como seus dados pessoais são tratados.
                            </p>

                            <h3>2.1 Dados Coletados</h3>
                            <ul>
                                <li>Dados de identificação: nome, e-mail, CPF (quando aplicável)</li>
                                <li>Dados de acesso: endereço IP, navegador, horários de acesso</li>
                                <li>Dados de uso: ações realizadas no sistema (logs de atividade)</li>
                            </ul>

                            <h3>2.2 Finalidade do Tratamento</h3>
                            <ul>
                                <li>Prestação do serviço de gestão empresarial</li>
                                <li>Segurança e auditoria do sistema</li>
                                <li>Comunicação sobre o serviço e atualizações</li>
                            </ul>

                            <h3>2.3 Seus Direitos (Art. 18 da LGPD)</h3>
                            <p>Você tem direito a:</p>
                            <ul>
                                <li><strong>Acesso:</strong> solicitar uma cópia de todos os seus dados pessoais</li>
                                <li><strong>Correção:</strong> solicitar a correção de dados incompletos ou inexatos</li>
                                <li><strong>Exclusão:</strong> solicitar a anonimização ou eliminação dos seus dados</li>
                                <li><strong>Portabilidade:</strong> exportar seus dados em formato estruturado</li>
                                <li><strong>Revogação:</strong> revogar o consentimento a qualquer momento</li>
                            </ul>
                            <p>
                                Para exercer esses direitos, acesse seu perfil ou entre em contato com o
                                administrador da sua empresa.
                            </p>

                            <h3>2.4 Armazenamento e Segurança</h3>
                            <p>
                                Os dados são armazenados em ambiente seguro com criptografia, controle de acesso
                                e isolamento por empresa (multi-tenant com banco de dados separado).
                                Backups são realizados periodicamente.
                            </p>

                            <h3>2.5 Compartilhamento</h3>
                            <p>
                                Os dados não são compartilhados com terceiros, exceto quando necessário para
                                prestação do serviço (ex: serviços de hospedagem) ou por determinação legal.
                            </p>

                            <h3>2.6 Retenção</h3>
                            <p>
                                Os dados são mantidos enquanto a conta estiver ativa. Após cancelamento,
                                os dados são retidos por 90 dias e então permanentemente excluídos.
                            </p>
                        </div>

                        {!hasAccepted && (
                            <div className="px-8 py-6 bg-gray-50 border-t">
                                <label className="flex items-start gap-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={agreed}
                                        onChange={(e) => setAgreed(e.target.checked)}
                                        className="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span className="text-sm text-gray-700">
                                        Li e concordo com os <strong>Termos de Uso</strong> e a{' '}
                                        <strong>Política de Privacidade</strong> acima apresentados.
                                    </span>
                                </label>

                                <button
                                    onClick={handleAccept}
                                    disabled={!agreed || processing}
                                    className="mt-4 w-full flex justify-center py-2.5 px-4 rounded-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                                >
                                    {processing ? 'Processando...' : 'Aceitar e Continuar'}
                                </button>
                            </div>
                        )}

                        {hasAccepted && (
                            <div className="px-8 py-4 bg-green-50 border-t text-center">
                                <p className="text-sm text-green-700">
                                    Termos aceitos. <a href="/dashboard" className="underline font-medium">Voltar ao sistema</a>
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
