import { Hero } from "@/components/sections/hero";
import { Programs } from "@/components/sections/programs";
import { NewsGrid } from "@/components/sections/news-grid";
import { Leaders } from "@/components/sections/leaders";
import { FAQ } from "@/components/sections/faq";
import { Statistics } from "@/components/sections/statistics";
import { Contact } from "@/components/sections/contact";

export default function HomePage() {
  return (
    <>
      <Hero />
      <Programs />
      <NewsGrid />
      <Leaders />
      <FAQ />
      <Statistics />
      <Contact />
    </>
  );
}
