import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Privacy({ tenant }) {
    return (
        <AuthenticatedLayout>
            <Head title="Política de Privacidade" />

            <div className="py-6">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="bg-white rounded-lg shadow p-8 prose prose-sm prose-gray max-w-none">
                        <h1>Política de Privacidade</h1>
                        <p className="text-gray-500">
                            {tenant?.name || 'Mercury'} &mdash; Última atualização: Abril 2026
                        </p>

                        <h2>1. Controlador dos Dados</h2>
                        <p>
                            A empresa {tenant?.name || 'contratante'} é a controladora dos dados pessoais
                            inseridos nesta plataforma, nos termos da LGPD (Lei nº 13.709/2018).
                        </p>

                        <h2>2. Dados Pessoais Tratados</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th>Dados</th>
                                    <th>Finalidade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Identificação</td>
                                    <td>Nome, e-mail, CPF</td>
                                    <td>Cadastro e autenticação</td>
                                </tr>
                                <tr>
                                    <td>Profissional</td>
                                    <td>Cargo, setor, loja, contrato</td>
                                    <td>Gestão de RH</td>
                                </tr>
                                <tr>
                                    <td>Acesso</td>
                                    <td>IP, navegador, horários</td>
                                    <td>Segurança e auditoria</td>
                                </tr>
                                <tr>
                                    <td>Uso</td>
                                    <td>Ações no sistema</td>
                                    <td>Logs de atividade</td>
                                </tr>
                            </tbody>
                        </table>

                        <h2>3. Base Legal</h2>
                        <ul>
                            <li><strong>Consentimento</strong> (Art. 7º, I) — para aceite dos termos</li>
                            <li><strong>Execução de contrato</strong> (Art. 7º, V) — para prestação do serviço</li>
                            <li><strong>Interesse legítimo</strong> (Art. 7º, IX) — para segurança e auditoria</li>
                        </ul>

                        <h2>4. Seus Direitos</h2>
                        <p>Conforme Art. 18 da LGPD, você pode:</p>
                        <ul>
                            <li>Exportar seus dados pessoais (perfil &rarr; Exportar meus dados)</li>
                            <li>Solicitar correção de dados (perfil &rarr; Editar)</li>
                            <li>Solicitar exclusão/anonimização (perfil &rarr; Excluir minha conta)</li>
                            <li>Revogar consentimento a qualquer momento</li>
                        </ul>

                        <h2>5. Contato do Encarregado (DPO)</h2>
                        <p>
                            Para exercer seus direitos ou esclarecer dúvidas sobre o tratamento de dados,
                            entre em contato com o administrador da sua empresa ou envie um e-mail para
                            o encarregado de dados (DPO) indicado pela sua organização.
                        </p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
