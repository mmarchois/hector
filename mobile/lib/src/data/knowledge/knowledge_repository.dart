import 'package:dio/dio.dart';

import '../../domain/conversation.dart';

/// Base documentaire : consultation d'un passage cité par une réponse.
class KnowledgeRepository {
  KnowledgeRepository(this._dio);

  final Dio _dio;

  /// État actuel d'un passage cité (`GET /api/v1/passages/{uuid}`) : sa
  /// version a pu être retirée depuis la réponse.
  Future<CitedPassage> passage(String uuid) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/api/v1/passages/$uuid',
    );

    return CitedPassage.fromJson(response.data!);
  }
}
