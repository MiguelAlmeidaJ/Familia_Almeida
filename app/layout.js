import './globals.css';

export const metadata = {
  title: 'Família Almeida Finanças',
  description: 'Gestão financeira da Família Almeida',
};

export default function RootLayout({ children }) {
  return (
    <html lang="pt-BR">
      <body>{children}</body>
    </html>
  );
}
