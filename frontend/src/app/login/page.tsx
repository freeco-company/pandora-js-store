import { redirect } from 'next/navigation';

// /login is kept as a permanent redirect to /account so the guest dashboard
// (with mascot, achievement preview, benefits) is always the single entry point.
export default function LoginPage() {
  redirect('/account');
}
