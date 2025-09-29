export default function EmployeeAvatar({
    employee = null,
    src = null,
    name = null,
    size = 'md',
    className = '',
    showTooltip = true,
    onClick = null
}) {
    // Tamanhos disponíveis
    const sizes = {
        xs: 'h-6 w-6 text-xs',
        sm: 'h-8 w-8 text-sm',
        md: 'h-10 w-10 text-base',
        lg: 'h-12 w-12 text-lg',
        xl: 'h-16 w-16 text-xl',
        '2xl': 'h-20 w-20 text-2xl',
        '3xl': 'h-24 w-24 text-3xl'
    };

    // Determinar dados do funcionário
    const employeeData = employee || { name: name || 'Employee', avatar_url: src };
    const avatarUrl = employeeData.avatar_url || src;
    const employeeName = employeeData.name || name || 'Employee';

    // Gerar iniciais
    const getInitials = (name) => {
        if (!name) return 'E';
        const words = name.split(' ');
        let initials = '';

        for (const word of words) {
            if (word && initials.length < 2) {
                initials += word.charAt(0).toUpperCase();
            }
        }

        return initials || 'E';
    };

    // Gerar cor de fundo baseada no nome
    const getBackgroundColor = (name) => {
        const colors = [
            'bg-blue-500', 'bg-purple-500', 'bg-red-500', 'bg-yellow-500',
            'bg-green-500', 'bg-orange-500', 'bg-indigo-500', 'bg-pink-500',
            'bg-lime-500', 'bg-cyan-500', 'bg-amber-500', 'bg-violet-500'
        ];

        if (!name) return colors[0];

        const index = name.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
        return colors[index % colors.length];
    };

    const initials = getInitials(employeeName);
    const bgColor = getBackgroundColor(employeeName);
    const sizeClass = sizes[size] || sizes.md;

    const AvatarContent = () => (
        <div
            className={`
                relative inline-flex items-center justify-center rounded-full
                ${sizeClass}
                ${onClick ? 'cursor-pointer hover:opacity-80 transition-opacity' : ''}
                ${className}
            `}
            onClick={onClick}
            title={showTooltip ? employeeName : undefined}
        >
            {avatarUrl ? (
                <>
                    <img
                        src={avatarUrl}
                        alt={employeeName}
                        className="h-full w-full rounded-full object-cover"
                        onError={(e) => {
                            // Se a imagem falhar ao carregar, esconder e mostrar fallback
                            e.target.style.display = 'none';
                            e.target.nextSibling.style.display = 'flex';
                        }}
                    />
                    {/* Fallback escondido inicialmente */}
                    <div
                        className={`
                            absolute inset-0 items-center justify-center rounded-full text-white font-medium
                            ${bgColor}
                        `}
                        style={{ display: 'none' }}
                    >
                        {initials}
                    </div>
                </>
            ) : (
                // Avatar com iniciais
                <div className={`
                    flex items-center justify-center rounded-full text-white font-medium
                    ${bgColor}
                    h-full w-full
                `}>
                    {initials}
                </div>
            )}
        </div>
    );

    // Se tiver tooltip e não for clicável, envolver em div com title
    if (showTooltip && !onClick) {
        return (
            <div title={employeeName}>
                <AvatarContent />
            </div>
        );
    }

    return <AvatarContent />;
}