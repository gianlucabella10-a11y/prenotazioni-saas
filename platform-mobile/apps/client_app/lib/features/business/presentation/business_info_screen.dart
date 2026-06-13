import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers.dart';

/// Business information: every datum comes from the tenant config — name,
/// locations, addresses, phones. Opening hours, social links and photo
/// gallery arrive with the backend P0 config extension
/// (GIUFFRIDA_FEATURE_GAP §3.4) and will render here without app changes
/// beyond the model.
class BusinessInfoScreen extends ConsumerWidget {
  const BusinessInfoScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(whiteLabelConfigProvider).value;
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Informazioni')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            config?.appName ?? '',
            style: theme.textTheme.headlineSmall,
            textAlign: TextAlign.center,
          ),
          if (config?.tagline != null) ...[
            const SizedBox(height: 4),
            Text(
              config!.tagline!,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium,
            ),
          ],
          const SizedBox(height: 16),
          for (final location in config?.locations ?? const [])
            Card(
              margin: const EdgeInsets.only(bottom: 12),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(location.name, style: theme.textTheme.titleMedium),
                    if (location.address != null) ...[
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          const Icon(Icons.place_outlined, size: 18),
                          const SizedBox(width: 8),
                          Expanded(child: Text(location.address!)),
                        ],
                      ),
                    ],
                    if (location.phone != null) ...[
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          const Icon(Icons.phone_outlined, size: 18),
                          const SizedBox(width: 8),
                          Text(location.phone!),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}
