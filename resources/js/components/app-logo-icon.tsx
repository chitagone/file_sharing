import type { ComponentProps } from 'react';
import { SiDocusaurus } from 'react-icons/si';

type IconProps = ComponentProps<typeof SiDocusaurus>;

export default function AppLogoIcon(props: IconProps) {
    return <SiDocusaurus {...props} />;
}
